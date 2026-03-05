<?php

namespace App\Rules;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Validation\ValidationRule;

class UniquePaymentDetail implements ValidationRule
{
    /**
     * The table to check for uniqueness.
     *
     * @var string
     */
    protected string $table = 'payment_details';

    /**
     * The column to check for uniqueness.
     *
     * @var string
     */
    protected string $column = 'detail';

    /**
     * The ID to ignore (optional).
     *
     * @var mixed
     */
    protected mixed $ignore = null;

    /**
     * The name of the ID column.
     *
     * @var string
     */
    protected string $idColumn = 'id';

    /**
     * Extra where clauses.
     *
     * @var array
     */
    protected array $wheres = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Таблица и колонка уже установлены по умолчанию
    }

    /**
     * Set the ID to ignore.
     *
     * @param  mixed  $id
     * @param  string  $idColumn
     * @return $this
     */
    public function ignore(mixed $id, string $idColumn = 'id'): static
    {
        $this->ignore = $id;
        $this->idColumn = $idColumn;

        return $this;
    }

    /**
     * Add a where clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @return $this
     */
    public function where(string $column, mixed $value): static
    {
        $this->wheres[] = [
            'column' => $column,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = DB::table($this->table)
            ->where($this->column, $value)
            ->whereNull('archived_at');

        if (! is_null($this->ignore)) {
            $query->where(function ($query) {
                $query->where($this->idColumn, '!=', $this->ignore)
                      ->orWhereNull($this->idColumn);
            });
        }

        foreach ($this->wheres as $where) {
            $query->where($where['column'], $where['value']);
        }

        if ($query->exists()) {
            $fail('Значение в поле :attribute уже существует среди активных реквизитов.');
        }
    }
}
