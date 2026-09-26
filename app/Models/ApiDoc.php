<?php

namespace App\Models;

use App\Casts\MetaDtoCast;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;

final class ApiDoc extends Model
{
    use Searchable;

    protected function casts(): array
    {
        return [
            'meta' => MetaDtoCast::class,
        ];
    }

    #[SearchUsingFullText(['title', 'content'])]
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'content' => $this->content,
        ];
    }
}
