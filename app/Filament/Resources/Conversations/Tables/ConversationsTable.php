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
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Unico\Core\Signature\PhoneNumber;

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
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(self::fornitoreNameQuery(), $direction)),
                TextColumn::make('flow')->label('Percorso')->formatStateUsing(fn (string $state) => self::FLOWS[$state] ?? $state),
                TextColumn::make('node')->label('Domanda corrente'),
                TextColumn::make('status')->label('Stato')->badge()->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state),
                TextColumn::make('messages_count')->label('Iterazioni')->counts('messages')->badge()->color('gray')->sortable(),
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
                SelectFilter::make('fornitore')->label('Produttore')->searchable()
                    ->options(fn () => Fornitore::query()->whereNotNull('tel')->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $tel = filled($data['value'] ?? null) ? Fornitore::find($data['value'])?->tel : null;
                        $national = filled($tel) ? PhoneNumber::national($tel) : '';

                        return filled($data['value'] ?? null)
                            ? $query->where('wa_number', 'like', '%'.($national !== '' ? $national : '#nessuno#'))
                            : $query;
                    }),
                SelectFilter::make('status')->label('Stato')->options(self::STATUSES),
                SelectFilter::make('flow')->label('Percorso')->options(self::FLOWS),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /** Il nome del produttore con lo stesso telefono (ultime 10 cifre), per ordinare la tabella. */
    private static function fornitoreNameQuery(): Expression
    {
        $fornitore = new Fornitore;
        $table = $fornitore->getConnection()->getDatabaseName().'.'.$fornitore->getTable();

        return DB::raw("(select min(f.name) from {$table} f where f.deleted_at is null and f.tel is not null and right(regexp_replace(f.tel, '[^0-9]', ''), 10) = right(conversations.wa_number, 10))");
    }
}
