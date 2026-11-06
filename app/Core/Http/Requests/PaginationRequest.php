<?php

declare(strict_types=1);

namespace App\Core\Http\Requests;

use App\Core\Domain\Pagination\PageRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the ?page and ?per_page query parameters of list endpoints.
 */
final class PaginationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . PageRequest::MAX_PER_PAGE],
        ];
    }

    public function pageRequest(): PageRequest
    {
        return new PageRequest(
            (int) $this->validated('page', 1),
            (int) $this->validated('per_page', PageRequest::DEFAULT_PER_PAGE)
        );
    }
}
