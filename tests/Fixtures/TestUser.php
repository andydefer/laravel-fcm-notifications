<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Fixtures;

use Andydefer\FcmNotifications\Contracts\HasFcmToken;
use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Traits\HasFcmNotifications;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;

/**
 * Test user model for FCM notification package testing.
 *
 * This fixture model implements the HasFcmToken contract to simulate
 * a notifiable entity in the testing environment. It uses the test_users
 * table created by the package's test migration.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @package Andydefer\FcmNotifications\Tests\Fixtures
 */
class TestUser extends Model implements HasFcmToken
{
    use HasFcmNotifications;
    use Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'test_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the FCM tokens associated with this user.
     *
     * This method defines the polymorphic relationship between the test user
     * and FCM tokens. It allows the HasFcmNotifications trait to manage
     * token operations through the standard Eloquent relationship.
     *
     * @return MorphMany
     */
    public function fcmTokens(): MorphMany
    {
        return $this->morphMany(
            related: FcmToken::class,
            name: 'tokenable'
        );
    }
}
