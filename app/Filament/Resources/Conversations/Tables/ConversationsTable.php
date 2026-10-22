<?php

namespace App\Filament\Resources\Conversations\Tables;

use App\Models\Fornitore;
use App\Support\CurrentCompany;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversationsTable
{
    public const FLOWS = ['richiesta' => 'Richiesta', 'perfezionamento' => 'Perfezionamento'];

    public const STATUSES = ['attiva' => 'Attiva', 'completata' => 'Completata', 'annullata' => 'Annullata'];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('wa_number')->label('Telefono')->searchable()->sortable(),
                TextColumn::make('fornitore')->label('Produttore')->placeholder('-')->state(fn ($record) => $record->fornitore?->display_name)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(Fornitore::nameByWhatsAppSql('conversations.wa_number'), $direction)),
                TextColumn::make('flow')->label('Percorso')->sortable()->formatStateUsing(fn (string $state) => self::FLOWS[$state] ?? $state),
                TextColumn::make('node')->label('Domanda corrente')->sortable(),
                TextColumn::make('status')->label('Stato')->badge()->sortable()->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state),
                TextColumn::make('messages_count')->label('Iterazioni')->counts('messages')->badge()->color('gray')->sortable(),
                TextColumn::make('loanRequest.code')->label('Pratica')->placeholder('-')->sortable(),
                TextColumn::make('updated_at')->label('Ultimo messaggio')->dateTime()->sortable(),
            ])
            ->filters([

                SelectFilter::make('flow')->label('Percorso')->options(self::FLOWS),
                CurrentCompany::filter(),
                SelectFilter::make('fornitore')->label('Produttore')->searchable()
                    ->options(fn () => Fornitore::query()->whereNotNull('tel')->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => Fornitore::whereWhatsAppOf($query, 'wa_number', $data['value'] ?? null)),
                SelectFilter::make('status')->label('Stato')->options(self::STATUSES),
                Filter::make('wa_number')->label('Telefono')
                    ->schema([TextInput::make('wa_number')->label('Telefono')])
                    ->query(fn ($query, array $data) => $query->when(
                        filled($data['wa_number'] ?? null),
                        fn ($query) => $query->where('wa_number', 'like', '%'.ltrim(preg_replace('/\D+/', '', $data['wa_number']), '0').'%'),
                    ))
                    ->indicateUsing(fn (array $data) => filled($data['wa_number'] ?? null) ? 'Telefono: '.$data['wa_number'] : null),

            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
