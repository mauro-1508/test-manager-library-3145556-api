<?php

it('responds ok on GET /api/v1/health without a database', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});
