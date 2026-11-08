<?php

namespace App\Livewire\Traits;

trait WithSafeTableControls
{
    private const ALLOWED_PAGE_SIZES = [10, 20, 50, 100];

    public function updatedPerPage($value): void
    {
        $this->perPage = $this->resolvePageSize($value);
        $this->resetPage();
    }

    /** @param list<string> $allowedFields */
    protected function changeSort(string $field, array $allowedFields): void
    {
        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /** @param list<string> $allowedFields */
    protected function resolveSortField(array $allowedFields, string $default): string
    {
        return in_array($this->sortField, $allowedFields, true)
            ? $this->sortField
            : $default;
    }

    protected function resolveSortDirection(): string
    {
        return $this->sortDirection === 'desc' ? 'desc' : 'asc';
    }

    protected function resolvePageSize(mixed $value = null): int
    {
        $value = (int) ($value ?? $this->perPage);

        return in_array($value, self::ALLOWED_PAGE_SIZES, true)
            ? $value
            : $this->defaultPageSize();
    }

    protected function defaultPageSize(): int
    {
        return 20;
    }
}
