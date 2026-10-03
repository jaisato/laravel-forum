#!/usr/bin/env node
// `npm audit` con una lista de excepciones. npm no tiene `--ignore`, así que
// este script ejecuta `npm audit --json`, cruza el informe con
// .github/npm-audit-allowlist.json y decide él el código de salida.
//
// Reglas, en el orden en que se aplican a cada entrada del informe:
//
// 1. Una entrada con avisos propios (su `via` trae objetos con GHSA) se tolera
//    solo si TODOS sus GHSA están en la lista para ese mismo paquete. Un GHSA
//    nuevo del mismo paquete hace fallar el job igual que uno de cualquier otro.
// 2. Si npm ofrece para ella un arreglo no rompedor (`fixAvailable` true, o un
//    objeto sin `isSemVerMajor`), la excepción ha caducado: el job falla y pide
//    actualizar y retirar la entrada. Un arreglo que exige un cambio de versión
//    mayor no cuenta como disponible; esa es justo la situación que la lista
//    documenta.
// 3. Una entrada sin avisos propios (su `via` solo nombra otros paquetes) es
//    el eco de un aviso ajeno: se tolera si todos los paquetes a los que apunta
//    se toleran. En esas entradas `fixAvailable` no se mira: npm lo deriva y a
//    veces lo da por bueno aunque la raíz no tenga parche.
//
// Todo lo demás falla. Sin dependencias, Node 22, sin instalar nada: `npm audit`
// trabaja sobre package-lock.json, igual que antes.

import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const allowlistPath = resolve(scriptDir, '..', 'npm-audit-allowlist.json');
const onActions = process.env.GITHUB_ACTIONS === 'true';

const GHSA = /^GHSA-[23456789cfghjmpqrvwx]{4}-[23456789cfghjmpqrvwx]{4}-[23456789cfghjmpqrvwx]{4}$/;
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

// En Actions salen como anotaciones (visibles en el resumen del run); los saltos
// de línea van codificados como %0A, que es lo que entiende el formato.
function fail(message) {
    console.error(onActions ? `::error title=npm audit::${message.replace(/\n/g, '%0A')}` : `ERROR: ${message}`);
}

function warn(message) {
    console.log(onActions ? `::warning title=npm audit::${message.replace(/\n/g, '%0A')}` : `AVISO: ${message}`);
}

// --- 1. La lista -----------------------------------------------------------

function loadAllowlist() {
    let parsed;
    try {
        parsed = JSON.parse(readFileSync(allowlistPath, 'utf8'));
    } catch (error) {
        fail(`no se puede leer ${allowlistPath}: ${error.message}`);
        process.exit(2);
    }

    const entries = parsed?.advisories;
    if (!Array.isArray(entries)) {
        fail(`${allowlistPath} debe tener una propiedad "advisories" con un array`);
        process.exit(2);
    }

    const byKey = new Map();
    entries.forEach((entry, index) => {
        const where = `entrada ${index + 1} de ${allowlistPath}`;
        const problems = [];
        if (!GHSA.test(entry?.id ?? '')) problems.push('"id" debe ser un identificador GHSA');
        if (typeof entry?.package !== 'string' || entry.package === '') problems.push('"package" es obligatorio');
        if (!ISO_DATE.test(entry?.added ?? '')) problems.push('"added" debe ser una fecha AAAA-MM-DD');
        if (typeof entry?.reason !== 'string' || entry.reason.trim().length < 20) {
            problems.push('"reason" debe explicar el motivo (al menos 20 caracteres)');
        }
        if (problems.length > 0) {
            fail(`${where}: ${problems.join('; ')}`);
            process.exit(2);
        }
        const key = `${entry.package}@${entry.id}`;
        if (byKey.has(key)) {
            fail(`${where}: ${key} está repetida`);
            process.exit(2);
        }
        byKey.set(key, { ...entry, used: false });
    });

    return byKey;
}

// --- 2. El informe ---------------------------------------------------------

function readReport() {
    // Para reproducir un informe guardado (o probar el script contra uno
    // retocado a mano) sin pasar por npm: NPM_AUDIT_JSON=informe.json.
    if (process.env.NPM_AUDIT_JSON) {
        try {
            return JSON.parse(readFileSync(process.env.NPM_AUDIT_JSON, 'utf8'));
        } catch (error) {
            fail(`no se puede leer NPM_AUDIT_JSON=${process.env.NPM_AUDIT_JSON}: ${error.message}`);
            process.exit(2);
        }
    }

    const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm';
    const result = spawnSync(npm, ['audit', '--json'], {
        cwd: repoRoot,
        encoding: 'utf8',
        maxBuffer: 64 * 1024 * 1024,
    });

    if (result.error) {
        fail(`no se pudo ejecutar npm audit: ${result.error.message}`);
        process.exit(2);
    }

    try {
        return JSON.parse(result.stdout);
    } catch {
        fail(`npm audit no devolvió JSON (código ${result.status}). stderr:\n${result.stderr}`);
        process.exit(2);
    }
}

function runAudit() {
    const report = readReport();

    // Un fallo del propio npm (sin lockfile, sin red, registro caído) llega
    // como JSON con "error". No es un aviso de seguridad, pero tampoco un pase.
    if (report?.error) {
        fail(`npm audit falló: ${report.error.code ?? ''} ${report.error.summary ?? JSON.stringify(report.error)}`.trim());
        process.exit(2);
    }

    if (report?.auditReportVersion !== 2) {
        fail(`formato de informe no soportado (auditReportVersion=${report?.auditReportVersion}); este script entiende el de npm >= 7`);
        process.exit(2);
    }

    return report;
}

function ghsaOf(advisory) {
    const match = /GHSA-[23456789cfghjmpqrvwx]{4}-[23456789cfghjmpqrvwx]{4}-[23456789cfghjmpqrvwx]{4}/.exec(advisory.url ?? '');
    return match ? match[0] : null;
}

function nonBreakingFixAvailable(entry) {
    const fix = entry.fixAvailable;
    if (fix === true) return true;
    return typeof fix === 'object' && fix !== null && fix.isSemVerMajor !== true;
}

// --- 3. El cruce -----------------------------------------------------------

function evaluate(report, allowlist) {
    const vulnerabilities = report.vulnerabilities ?? {};
    const verdicts = new Map(); // nombre -> { tolerated, reasons[], chain[] }

    function verdictFor(name, trail) {
        if (verdicts.has(name)) return verdicts.get(name);
        if (trail.includes(name)) {
            // Ciclo en el grafo de `via`: no se tolera nada que dependa de sí mismo.
            return { tolerated: false, reasons: [`ciclo en via: ${[...trail, name].join(' -> ')}`], chain: [] };
        }
        const entry = vulnerabilities[name];
        if (!entry) {
            return { tolerated: false, reasons: [`"${name}" aparece en via pero no en el informe`], chain: [] };
        }

        const own = entry.via.filter((via) => typeof via === 'object' && via !== null);
        const inherited = entry.via.filter((via) => typeof via === 'string');
        const reasons = [];
        const chain = [];

        for (const advisory of own) {
            const id = ghsaOf(advisory);
            const label = `${id ?? advisory.url ?? `source ${advisory.source}`} (${advisory.title}; ${advisory.range})`;
            if (!id) {
                reasons.push(`aviso sin GHSA, no se puede tolerar: ${label}`);
                continue;
            }
            const allowed = allowlist.get(`${name}@${id}`);
            if (!allowed) {
                reasons.push(`${label} no está en la lista de excepciones`);
                continue;
            }
            allowed.used = true;
            chain.push(`${id}, tolerado desde ${allowed.added}`);
        }

        if (own.length > 0 && reasons.length === 0 && nonBreakingFixAvailable(entry)) {
            reasons.push(
                `npm ya ofrece un arreglo no rompedor (fixAvailable=${JSON.stringify(entry.fixAvailable)}): actualiza y retira la excepción`,
            );
        }

        for (const parent of inherited) {
            const parentVerdict = verdictFor(parent, [...trail, name]);
            if (parentVerdict.tolerated) {
                chain.push(`vía ${parent}`);
            } else {
                reasons.push(`hereda el aviso de ${parent}, que no se tolera`);
            }
        }

        const verdict = { tolerated: reasons.length === 0, reasons, chain };
        verdicts.set(name, verdict);
        return verdict;
    }

    for (const name of Object.keys(vulnerabilities)) verdictFor(name, []);
    return verdicts;
}

// --- 4. La salida ----------------------------------------------------------

function printTable(rows, headers) {
    const widths = headers.map((header, i) => Math.max(header.length, ...rows.map((row) => String(row[i]).length)));
    const line = (cells) => cells.map((cell, i) => String(cell).padEnd(widths[i])).join('  ').trimEnd();
    console.log(line(headers));
    console.log(widths.map((width) => '-'.repeat(width)).join('  '));
    for (const row of rows) console.log(line(row));
}

function main() {
    const allowlist = loadAllowlist();
    const report = runAudit();
    const verdicts = evaluate(report, allowlist);
    const vulnerabilities = report.vulnerabilities ?? {};
    const names = Object.keys(vulnerabilities).sort();

    if (names.length === 0) {
        console.log('npm audit: sin avisos.');
    }

    const tolerated = names.filter((name) => verdicts.get(name).tolerated);
    const rejected = names.filter((name) => !verdicts.get(name).tolerated);

    if (tolerated.length > 0) {
        console.log(`\nAvisos tolerados por .github/npm-audit-allowlist.json (${tolerated.length}):\n`);
        printTable(
            tolerated.map((name) => [
                name,
                vulnerabilities[name].severity,
                vulnerabilities[name].range,
                verdicts.get(name).chain.join('; '),
            ]),
            ['Paquete', 'Severidad', 'Rango afectado', 'Por qué se tolera'],
        );
        for (const entry of allowlist.values()) {
            if (entry.used) {
                console.log(`\n${entry.id} (${entry.package}), añadida el ${entry.added}:\n  ${entry.reason}`);
                warn(`${entry.package}: ${entry.id} tolerado desde ${entry.added} (ver .github/npm-audit-allowlist.json)`);
            }
        }
    }

    for (const entry of allowlist.values()) {
        if (!entry.used) {
            warn(`la excepción ${entry.id} (${entry.package}) ya no coincide con ningún aviso: puede retirarse de la lista`);
        }
    }

    if (rejected.length > 0) {
        console.log(`\nAvisos NO tolerados (${rejected.length}):\n`);
        for (const name of rejected) {
            const entry = vulnerabilities[name];
            console.log(`- ${name} [${entry.severity}] ${entry.range}  (${entry.nodes.join(', ')})`);
            for (const reason of verdicts.get(name).reasons) console.log(`    ${reason}`);
            for (const via of entry.via) {
                if (typeof via === 'object' && via !== null) console.log(`    ${via.url}`);
            }
        }
        fail(`${rejected.length} paquete(s) con avisos fuera de la lista de excepciones; ejecuta "npm audit" para el detalle`);
        process.exit(1);
    }

    console.log(`\nnpm audit: ${names.length} entrada(s) en el informe, todas toleradas; nada fuera de la lista.`);
}

main();
