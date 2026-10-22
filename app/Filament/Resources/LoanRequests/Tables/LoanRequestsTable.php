<?php

namespace App\Filament\Resources\LoanRequests\Tables;

use App\Models\Attachment;
use App\Models\Fornitore;
use App\Models\LoanRequest;
use App\Services\Crm\LoanEmailSender;
use App\Support\CurrentCompany;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Unico\Core\Signature\PhoneNumber;

class LoanRequestsTable
{
    /**
     * Nome dei produttori per telefono (forma nazionale), letto una volta per richiesta.
     *
     * @return array<string, string>
     */
    private static function agentNames(): array
    {
        if (! app()->bound(self::class)) {
            app()->scoped(self::class, fn () => Fornitore::query()->whereNotNull('tel')->get()
                ->mapWithKeys(fn (Fornitore $f) => [PhoneNumber::national($f->tel) => $f->display_name])
                ->filter()->all());
        }

        return app(self::class);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Codice')->searchable()->sortable(),
                TextColumn::make('is_test')->label('Origine')->badge()->sortable()->color(fn (bool $state) => $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Prova' : 'Reale'),
                TextColumn::make('agent_wa_number')->label('Agente')->searchable()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(Fornitore::nameByWhatsAppSql('loan_requests.agent_wa_number'), $direction))
                    ->state(fn (LoanRequest $record) => self::agentNames()[PhoneNumber::national($record->agent_wa_number)] ?? $record->agent_wa_number)
                    ->tooltip(fn (LoanRequest $record) => $record->agent_wa_number),
                TextColumn::make('product')->label('Prodotto')->sortable()
                    ->formatStateUsing(fn (string $state, LoanRequest $record) => LoanRequest::productLabels($record->company_id)[$state] ?? $state),
                TextColumn::make('signature_status')->label('Firma')->badge()->sortable()->toggleable()
                    ->formatStateUsing(fn (?string $state) => LoanRequest::SIGNATURE_STATUSES[$state] ?? '-'),
                TextColumn::make('status')->label('Stato')->badge()->sortable()
                    ->formatStateUsing(fn (string $state) => LoanRequest::STATUSES[$state] ?? $state),
                TextColumn::make('privacy_received_at')->label('Informativa')->dateTime()->sortable()->placeholder('-'),
                TextColumn::make('perfected_at')->label('Perfezionata')->dateTime()->sortable()->placeholder('-'),
                TextColumn::make('attachments_sum_ai_cost')->label('Costo AI')->sum('attachments', 'ai_cost')->sortable()
                    ->formatStateUsing(fn ($state) => Attachment::formatCost($state))->placeholder('-')->toggleable(),
                TextColumn::make('emailed_at')->label('Inviata per email')->dateTime()->sortable()->placeholder('-')->toggleable(),
                TextColumn::make('created_at')->label('Creata')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                CurrentCompany::filter(),
                SelectFilter::make('agent')->label('Agente')->searchable()
                    ->options(fn () => Fornitore::query()->whereNotNull('tel')->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => Fornitore::whereWhatsAppOf($query, 'agent_wa_number', $data['value'] ?? null)),
                SelectFilter::make('status')->label('Stato')->options(LoanRequest::STATUSES),
                TernaryFilter::make('is_test')->label('Origine')->trueLabel('Prova')->falseLabel('Reali'),
                SelectFilter::make('product')->label('Prodotto')->options(fn () => LoanRequest::productLabels(CurrentCompany::id())),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('inviaEmail')->label('Invia per email (forza)')->icon('heroicon-o-envelope')->color('warning')
                        ->requiresConfirmation()->modalDescription('Manda all\'istruttoria dati e allegati delle pratiche selezionate, anche se già inviate.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $sender = app(LoanEmailSender::class);
                            $failed = $records->reject(fn (LoanRequest $loan) => $sender->send($loan));

                            $failed->isEmpty()
                                ? Notification::make()->title($records->count().' pratiche inviate per email')->success()->send()
                                : Notification::make()->title('Invio non riuscito per: '.$failed->pluck('code')->implode(', '))
                                    ->body($sender->recipient() ? 'Controlla la configurazione della posta.' : 'Manca l\'email dell\'istruttoria nella scheda Azienda.')->danger()->persistent()->send();
                        }),
                    DeleteBulkAction::make()->modalDescription('Elimina le pratiche selezionate con allegati, documenti e simulazioni collegati. Le conversazioni restano, senza pratica.'),
                ]),
            ]);
    }
}
