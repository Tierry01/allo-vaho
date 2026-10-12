<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Living reference of the back-office design system: tokens, typography and
 * components, rendered with sample values only. It carries no business data
 * and is only reachable in the local environment.
 */
class DesignSystem extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.design-system';

    protected static ?string $title = 'Design system';

    protected static ?string $slug = 'design-system';

    protected static ?int $navigationSort = 100;

    public static function getNavigationIcon(): string
    {
        return 'lucide-palette';
    }

    public static function canAccess(): bool
    {
        return app()->environment('local');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => [
                1 => ['label' => 'Exemple A', 'status' => 'Actif', 'tone' => 'success', 'amount' => 2500],
                2 => ['label' => 'Exemple B', 'status' => 'En attente', 'tone' => 'warning', 'amount' => 12000],
                3 => ['label' => 'Exemple C', 'status' => 'Rejeté', 'tone' => 'danger', 'amount' => 500],
            ])
            ->columns([
                TextColumn::make('label')->label('Libellé'),
                TextColumn::make('status')->label('Statut')->badge()->color(fn (array $record): string => $record['tone']),
                TextColumn::make('amount')->label('Montant')->numeric(locale: 'fr')->suffix(' FCFA')->alignEnd(),
            ])
            ->paginated(false);
    }
}
