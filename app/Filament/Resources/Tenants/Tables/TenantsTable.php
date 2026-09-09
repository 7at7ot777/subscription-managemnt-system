<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Tables;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Filament\Resources\Tenants\Actions\ImpersonateTenantUserAction;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->sortable()->copyable(),

                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('provisioning_status')->label('Provisioning')->badge()->toggleable(),

                TextColumn::make('subscription_status')
                    ->label('Subscription')
                    ->badge()
                    ->getStateUsing(fn (Tenant $record): SubscriptionStatus => $record->subscriptionStatus())
                    ->color(fn (SubscriptionStatus $state): string => $state->color()),

                TextColumn::make('subscription_start_at')->dateTime()->sortable()->toggleable(),
                TextColumn::make('subscription_end_at')->dateTime()->sortable()
                    ->placeholder('No expiry'),

                TextColumn::make('created_at')->dateTime()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // tenancy_db_password is deliberately absent. A TextColumn would read it
                // via getAttribute(), which bypasses the model's $hidden.
            ])
            ->filters([
                SelectFilter::make('status')->options(TenantStatus::class)->multiple(),

                SelectFilter::make('subscription_status')
                    ->label('Subscription')
                    ->options(SubscriptionStatus::class)
                    ->query(function (Builder $query, array $data): Builder {
                        $now = now();

                        return match ($data['value'] ?? null) {
                            SubscriptionStatus::Active->value => $query
                                ->where(fn (Builder $q) => $q->whereNull('subscription_start_at')->orWhere('subscription_start_at', '<=', $now))
                                ->where(fn (Builder $q) => $q->whereNull('subscription_end_at')->orWhere('subscription_end_at', '>=', $now)),
                            SubscriptionStatus::Expired->value => $query
                                ->whereNotNull('subscription_end_at')->where('subscription_end_at', '<', $now),
                            SubscriptionStatus::NotStarted->value => $query
                                ->whereNotNull('subscription_start_at')->where('subscription_start_at', '>', $now),
                            default => $query,
                        };
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ImpersonateTenantUserAction::make(),

                ActionGroup::make([
                    Action::make('toggleActive')
                        ->label(fn (Tenant $record): string => $record->status === TenantStatus::Active ? 'Deactivate' : 'Activate')
                        ->icon(fn (Tenant $record) => $record->status === TenantStatus::Active
                            ? Heroicon::OutlinedPause
                            : Heroicon::OutlinedPlay)
                        ->color(fn (Tenant $record): string => $record->status === TenantStatus::Active ? 'warning' : 'success')
                        ->requiresConfirmation()
                        ->modalDescription(fn (Tenant $record): ?string => $record->status === TenantStatus::Active
                            ? "Deactivating locks every user out of /{$record->slug}/app immediately."
                            : null)
                        ->action(fn (Tenant $record) => $record->update([
                            'status' => $record->status === TenantStatus::Active
                                ? TenantStatus::Suspended
                                : TenantStatus::Active,
                        ]))
                        ->successNotificationTitle('Tenant status updated'),

                    DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete tenant and drop its database')
                        ->modalDescription(fn (Tenant $record): string => "This permanently drops the tenant's database. Type “{$record->slug}” to confirm.")
                        ->schema([
                            TextInput::make('confirm_slug')
                                ->label('Confirm the tenant slug')
                                ->required()
                                ->rules(fn (Tenant $record) => ['in:'.$record->slug]),
                        ])
                        ->modalSubmitActionLabel('Permanently delete'),
                ])->label('More'),
            ])
            ->toolbarActions([
                // No bulk delete: TenantDeleted fires DeleteDatabase, so a mis-clicked
                // "select all" would drop many tenant databases irreversibly.
            ])
            ->defaultSort('created_at', 'desc');
    }
}
