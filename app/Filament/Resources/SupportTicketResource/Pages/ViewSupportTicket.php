<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Support\Services\SupportService;
use App\Filament\Resources\SupportTicketResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label(__('support.reply'))
                ->icon('heroicon-o-chat-bubble-left')
                ->color('primary')
                ->form([
                    Forms\Components\RichEditor::make('message_text')
                        ->label(__('support.message'))
                        ->required(),
                    Forms\Components\Toggle::make('is_internal_note')
                        ->label(__('support.internal_note'))
                        ->helperText(__('support.internal_note_help')),
                ])
                ->action(function (array $data) {
                    $admin = auth('admin')->user();
                    app(SupportService::class)->adminAddMessage(
                        $this->record,
                        $admin->id,
                        $data['message_text'],
                        $data['is_internal_note'] ?? false,
                    );
                    Notification::make()->success()->title(__('support.message_sent'))->send();
                    $this->refreshFormData(['messages']);
                })
                ->visible(fn () => auth('admin')->user()?->hasAnyPermission(['tickets.respond'])),

            Actions\Action::make('escalate')
                ->label(__('support.escalate'))
                ->icon('heroicon-o-fire')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('support.escalate_confirm'))
                ->action(function () {
                    $admin = auth('admin')->user();
                    $this->record->update(['priority' => 'critical']);
                    app(SupportService::class)->adminAddMessage(
                        $this->record,
                        $admin->id,
                        __('support.escalated_note'),
                        true,
                    );
                    Notification::make()->warning()->title(__('support.ticket_escalated'))->send();
                    $this->refreshFormData(['priority', 'messages']);
                })
                ->visible(fn () => $this->record->priority->value !== 'critical'
                    && auth('admin')->user()?->hasAnyPermission(['tickets.respond'])),

            Actions\Action::make('resolve')
                ->label(__('support.resolve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $admin = auth('admin')->user();
                    app(SupportService::class)->changeStatus($this->record, TicketStatus::Resolved, $admin->id);
                    Notification::make()->success()->title(__('support.ticket_resolved'))->send();
                    $this->refreshFormData(['status', 'resolved_at']);
                })
                ->visible(fn () => !in_array($this->record->status, [TicketStatus::Resolved, TicketStatus::Closed])
                    && auth('admin')->user()?->hasAnyPermission(['tickets.respond'])),

            Actions\Action::make('close')
                ->label(__('support.close'))
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    $admin = auth('admin')->user();
                    app(SupportService::class)->changeStatus($this->record, TicketStatus::Closed, $admin->id);
                    Notification::make()->success()->title(__('support.ticket_closed'))->send();
                    $this->refreshFormData(['status', 'closed_at']);
                })
                ->visible(fn () => $this->record->status !== TicketStatus::Closed
                    && auth('admin')->user()?->hasAnyPermission(['tickets.respond'])),

            Actions\EditAction::make(),
        ];
    }
}
