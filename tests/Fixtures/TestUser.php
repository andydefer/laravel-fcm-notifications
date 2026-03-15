<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Fixtures;

use Andydefer\FcmNotifications\Contracts\HasFcmToken;
use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Traits\HasFcmNotifications;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;

class TestUser extends Model implements HasFcmToken
{
    use HasFcmNotifications;
    use Notifiable;

    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the FCM tokens relationship.
     * Cette méthode est déjà fournie par le trait HasFcmNotifications,
     * mais on peut la surcharger si besoin.
     */
    public function fcmTokens(): MorphMany
    {
        return $this->morphMany(FcmToken::class, 'tokenable');
    }
}
