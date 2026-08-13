<?php

declare(strict_types=1);

use App\Livewire\Traits\AuthorizesPersonActions;
use App\Models\Couple;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use AuthorizesPersonActions;
    use Interactions;

    // ------------------------------------------------------------------------------
    public Person $person;

    // ------------------------------------------------------------------------------
    #[On('couple_added')]
    #[On('couple_updated')]
    #[On('couple_deleted')]
    public function refreshPartners(): void
    {
        // optionally refresh any data here
        // Livewire will re-render automatically
    }

    // ------------------------------------------------------------------------------
    public function confirm(int $id, string $name): void
    {
        $this->authorizePermission('couple:delete');

        $this->dialog()
            ->question(__('app.attention') . '!', __('app.are_you_sure'))
            ->confirm(__('app.delete_yes'))
            ->cancel(__('app.cancel'))
            ->hook([
                'ok' => [
                    'method' => 'delete',
                    'params' => [
                        'id'   => $id,
                        'name' => $name,
                    ],
                ],
            ])
            ->send();
    }

    public function delete(array $couple_data): void
    {
        $this->authorizePermission('couple:delete');

        $couple = Couple::query()
            ->where(function (Builder $q): void {
                $q->where('person1_id', $this->person->id)
                    ->orWhere('person2_id', $this->person->id);
            })->findOrFail($couple_data['id']);

        $couple->delete();

        $this->toast()->success(__('app.delete'), e($couple['name']) . ' ' . __('app.deleted') . '.')->send();

        $this->dispatch('couple_deleted');
    }
};
