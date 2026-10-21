<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Models\Company;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ragione sociale')->searchable(),
                TextColumn::make('whatsapp_number')->label('WhatsApp')->placeholder('-')->state(fn (Company $record) => $record->whatsappNumber())->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->url(fn (Company $record) => filled($record->whatsappNumber()) ? route('filament.admin.resources.conversations.index', [
                        'filters' => [
                            'company_id' => ['value' => $record->id],
                            'wa_number' => ['wa_number' => $record->whatsappNumber()],
                        ],
                    ]) : null)
                    ->openUrlInNewTab(),
                TextColumn::make('type')->label('Settore')->badge()->formatStateUsing(fn (string $state) => Company::TYPES[$state] ?? $state),
                ToggleColumn::make('is_active')->label('Attiva'),
                TextColumn::make('is_trial')->label('Prova')->badge()->color(fn (bool $state) => $state ? 'warning' : 'success')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Trial' : 'Contratto'),
                TextColumn::make('trialend_at')->label('Fine prova')->date()->placeholder('-'),
                TextColumn::make('address')->label('Sede')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')->label('Email privacy'),
                TextColumn::make('updated_at')->label('Aggiornata')->dateTime(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
