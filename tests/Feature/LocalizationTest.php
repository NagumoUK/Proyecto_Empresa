<?php

test('Spanish is the application default and profile messages are translated', function () {
    expect(app()->getLocale())->toBe('es')
        ->and(__('Profile updated.'))->toBe('Perfil actualizado.');
});
