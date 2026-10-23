import { reactive } from 'vue';
import type { Mock } from 'vitest';

export type FakeForm<T extends Record<string, unknown>> = T & {
    errors: Partial<Record<keyof T, string>>;
    processing: boolean;
    post: Mock;
    put: Mock;
    patch: Mock;
    delete: Mock;
    reset: Mock;
    clearErrors: Mock;
};

/**
 * Every form created through the fake, latest last.
 */
export const forms: FakeForm<Record<string, unknown>>[] = [];

/**
 * A stand-in for Inertia's useForm(): the fields, errors and processing
 * flag are reactive, and the submit methods are spies whose options (for
 * example onSuccess) the test can call.
 */
export function fakeUseForm<T extends Record<string, unknown>>(
    data: T,
): FakeForm<T> {
    const defaults = { ...data };
    const form = reactive({
        ...data,
        errors: {},
        processing: false,
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        reset: vi.fn(() => Object.assign(form, defaults)),
        clearErrors: vi.fn(() => {
            form.errors = {};
        }),
    }) as unknown as FakeForm<T>;

    forms.push(form as unknown as FakeForm<Record<string, unknown>>);

    return form;
}

/**
 * Runs the onSuccess callback given to the latest call of a submit spy.
 */
export function succeed(submit: Mock): void {
    const options = submit.mock.calls.at(-1)?.[1] as
        { onSuccess?: () => void } | undefined;

    options?.onSuccess?.();
}
