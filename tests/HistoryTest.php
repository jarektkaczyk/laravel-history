<?php

namespace Sofa\History\Tests;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Sofa\History\History;

class HistoryTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->prepareTables();
        config(['sofa_history.user_model' => User::class]);
    }

    /** @test */
    public function records_history_for_different_models()
    {
        $john = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.net',
        ]);
        $john->update([
            'name' => 'John Delano Doe',
            'phone' => '9876543210',
        ]);

        $jane = User::create([
            'name' => 'Jane Doe',
        ]);
        $jane->update([
            'phone' => '1122334455',
            'email' => 'jane@example.net',
        ]);
        $jane->update([
            'phone' => '5544332211',
        ]);
        $jane->update([
            'name' => 'Jane Angeline Doe',
            'email' => 'jane@example.com',
        ]);
        $jane->delete();

        $john->delete();
        $john = User::withTrashed()->where('id', $john->getKey())->first();
        $john->restore();

        $this->assertEquals(9, History::count());
        $this->assertEquals(4, History::for($john)->count());
        $this->assertEquals(5, History::for($jane)->count());
    }

    /** @test */
    public function recreates_given_version()
    {
        $john = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.net',
        ]);
        $john->update([
            'name' => 'John Delano Doe',
            'phone' => '9876543210',
        ]);
        $john->delete();
        $john = User::withTrashed()->where('id', $john->getKey())->first();
        $john->restore();

        /** @var User $johnFromThePast */
        $johnFromThePast = History::recreate(User::class, $john->id, 1);
        $this->assertSame('John Doe', $johnFromThePast->name);
        $this->assertNull($johnFromThePast->phone);

        $johnFromThePast = History::recreate(User::class, $john->id, 3);
        $this->assertSame('John Delano Doe', $johnFromThePast->name);
        $this->assertSame('9876543210', $johnFromThePast->phone);
        $this->assertTrue($johnFromThePast->trashed());

        $johnFromThePast = History::recreate(User::class, $john->id, 4);
        $this->assertSame('John Delano Doe', $johnFromThePast->name);
        $this->assertFalse($johnFromThePast->trashed());
    }

    /** @test */
    public function recreates_at_given_time()
    {
        $this->timeTravel('2020-12-01');
        $john = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.net',
        ]);
        $this->timeTravel('2020-12-05');
        $john->update([
            'name' => 'John Delano Doe',
            'phone' => '9876543210',
        ]);
        $this->timeTravel('2020-12-10');
        $john->delete();

        $this->timeTravel('2020-12-15');
        $john = User::withTrashed()->where('id', $john->getKey())->first();
        $john->restore();

        $this->timeTravel('2020-12-25');
        $john = User::withTrashed()->where('id', $john->getKey())->first();
        $john->forceDelete();

        $this->assertNull(History::recreate(User::class, $john->id, '2020-11-30'));

        /** @var User $johnFromThePast */
        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-20');
        $this->assertSame('John Delano Doe', $johnFromThePast->name);
        $this->assertSame('9876543210', $johnFromThePast->phone);

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-10');
        $this->assertSame('John Delano Doe', $johnFromThePast->name);
        $this->assertTrue($johnFromThePast->trashed());

        $this->assertNull(History::recreate(User::class, $john->id, today()));
        $this->assertNull(History::recreate(User::class, $john->id, '2020-12-26'));
    }

    /** @test */
    public function non_soft_deletable_model()
    {
        $post = Post::create(['title' => 'Lazy dog']);
        $post->update(['body' => 'The quick brown fox jumps...']);
        $post->delete();

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, 2);
        $this->assertSame('Lazy dog', $postFromThePast->title);
        $this->assertSame('The quick brown fox jumps...', $postFromThePast->body);
        $this->assertNull(History::recreate(Post::class, $post->id, 3));
    }

    /** @test */
    public function custom_user_resolvers()
    {
        $user = User::create(['name' => 'logged in user']);
        config(['sofa_history.user_resolver' => fn () => $user->id]);

        $john = User::create(['name' => 'John Doe']);
        $this->assertTrue(History::for($john)->latest()->first()->user->is($user));

        config(['sofa_history.user_resolver' => fn () => $user]);
        $john->update(['name' => 'John Delano Doe']);
        $this->assertTrue(History::for($john)->latest()->first()->user->is($user));

        config(['sofa_history.user_resolver' => fn () => $this->dummyAuthenticatable($user)]);

        $john->update(['name' => 'John Doe']);
        $this->assertTrue(History::for($john)->latest()->first()->user->is($user));

        config(['sofa_history.user_resolver' => fn () => (object) ['id' => $user->id]]);
        $john->update(['name' => 'John Dean Doe']);
        $this->assertTrue(History::for($john)->latest()->first()->user->is($user));
    }

    private function timeTravel($time): void
    {
        Carbon::setTestNow(Carbon::parse($time)->startOfDay());
        CarbonImmutable::setTestNow(CarbonImmutable::parse($time)->startOfDay());
    }

    private function prepareTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title')->nullable();
            $table->string('body')->nullable();
            $table->timestamps();
        });
    }

    private function dummyAuthenticatable(User $user): Authenticatable
    {
        return new class($user) implements Authenticatable {
            use AuthenticatableTrait;

            public function __construct($user)
            {
                $this->user = $user;
            }

            public function getAuthIdentifier()
            {
                return $this->user->id;
            }
        };
    }
}

/**
 * @property string $name
 * @property string $email
 * @property string $phone
 * @mixin Builder
 */
class User extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
    ];
}

/**
 * @property string $title
 * @property string $body
 */
class Post extends Model
{
    protected $fillable = [
        'title',
        'body',
        'user_id',
    ];
}
