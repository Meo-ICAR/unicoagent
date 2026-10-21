<?php

namespace App\Filament\Resources\Conversations\Tables;

use App\Support\CurrentCompany;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                TextColumn::make('fornitore')->label('Produttore')->placeholder('-')->state(fn ($record) => $record->fornitore?->display_name),
                TextColumn::make('flow')->label('Percorso')->formatStateUsing(fn (string $state) => self::FLOWS[$state] ?? $state),
                TextColumn::make('node')->label('Domanda corrente'),
                TextColumn::make('status')->label('Stato')->badge()->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state),
                TextColumn::make('loanRequest.code')->label('Pratica')->placeholder('-'),
                TextColumn::make('updated_at')->label('Ultimo messaggio')->dateTime()->sortable(),
            ])
            ->filters([
                CurrentCompany::filter(),
                Filter::make('wa_number')->label('Telefono')
                    ->schema([TextInput::make('wa_number')->label('Telefono')])
                    ->query(fn ($query, array $data) => $query->when(
                        filled($data['wa_number'] ?? null),
                        fn ($query) => $query->where('wa_number', 'like', '%'.ltrim(preg_replace('/\D+/', '', $data['wa_number']), '0').'%'),
                    ))
                    ->indicateUsing(fn (array $data) => filled($data['wa_number'] ?? null) ? 'Telefono: '.$data['wa_number'] : null),
                SelectFilter::make('status')->label('Stato')->options(self::STATUSES),
                SelectFilter::make('flow')->label('Percorso')->options(self::FLOWS),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
