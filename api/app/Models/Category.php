<?php

namespace App\Models;

use App\Casts\EncryptedText;
use App\Models\Concerns\BelongsToUser;
use App\Support\LedgerCipher;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * name is ciphertext in the table, so uniqueness lives on name_index, a per-user
 * fingerprint of the plain name. user_id is added in a foreach over table names,
 * which Larastan's migration reader can't follow.
 *
 * @property string $name
 * @property string $name_index
 * @property int $user_id
 */
#[Fillable(['name', 'color', 'emoji'])]
class Category extends Model
{
    use BelongsToUser;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name' => EncryptedText::class,
        ];
    }

    // creating, not saving: saving fires before BelongsToUser's creating stamp sets
    // user_id, and booted() listeners run after the trait's, so the owner is known here.
    protected static function booted(): void
    {
        static::creating(fn (Category $category) => $category->fingerprintName());

        static::updating(function (Category $category) {
            if ($category->isDirty(['name', 'user_id'])) {
                $category->fingerprintName();
            }
        });
    }

    // No owner yet means none was set and nobody is signed in: name_index stays empty
    // and the NOT NULL column refuses the row, like the user_id column does.
    private function fingerprintName(): void
    {
        $user_id = $this->getAttribute('user_id');

        if ($user_id !== null) {
            $this->setAttribute('name_index', LedgerCipher::fingerprint((int) $user_id, $this->name));
        }
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<Recurring, $this> */
    public function recurring(): HasMany
    {
        return $this->hasMany(Recurring::class);
    }
}
