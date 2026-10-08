<?php

declare(strict_types=1);

use App\Models\Person;
use Livewire\Livewire;

test('a child from another team cannot be linked by directly calling the component', function (): void {
    /** @var Tests\TestCase $this */
    $manager = $this->memberWithRole('manager');
    $parent  = Person::factory()->create([
        'sex'     => 'm',
        'team_id' => $manager->current_team_id,
    ]);
    $foreignChild = Person::factory()->create();

    $this->actingAs($manager);

    Livewire::test('people::add.child', ['person' => $parent])
        ->set('form.person_id', $foreignChild->id)
        ->call('saveChild')
        ->assertStatus(404);

    expect($foreignChild->fresh()->father_id)->toBeNull();
});
