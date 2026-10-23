<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesVisibleContent;
use App\Models\Post;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePostRequest extends FormRequest
{
    use AuthorizesVisibleContent;

    public function authorize(): Response
    {
        return $this->inspectVisible('update', $this->editedPost());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['body' => PostBodyRules::rules()];
    }

    public function editedPost(): Post
    {
        /** @var Post */
        return $this->route('post');
    }
}
