<?php

declare(strict_types=1);

it('starts the page in the colour scheme of the appearance cookie', function (string $appearance) {
    $this->withUnencryptedCookie('appearance', $appearance)
        ->get(route('home'))
        ->assertOk()
        ->assertSee("const appearance = '{$appearance}';", false);
})->with(['light', 'dark', 'system']);

it('starts the page in the system colour scheme without the cookie', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee("const appearance = 'system';", false);
});

it('ignores an appearance cookie it does not know', function () {
    // The cookie is not encrypted: whatever wrote it, only a known value
    // reaches the inline script of the page.
    $this->withUnencryptedCookie('appearance', "dark';alert(1)//")
        ->get(route('home'))
        ->assertOk()
        ->assertSee("const appearance = 'system';", false)
        ->assertDontSee('alert(1)', false);
});
