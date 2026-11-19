<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_guests_to_login_and_health_endpoint_is_up(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/up')->assertOk();
    }
}
