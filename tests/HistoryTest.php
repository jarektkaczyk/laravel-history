<?php

namespace Sofa\History\Tests;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Sofa\History\History;

/**
 * TODO functional
 * - [ ] configurable, automatic retention period
 * - [ ] integration helper commands (BTM PivotEvents, mixin docblock, more?)
 * - [ ] support BelongsToMany::detach() run on the query directly. Low prio, workaround exists
 * - [ ] previous/next version. Cool, but low prio
 * - [ ] recreate 'hasOne|morhOne' relation without single sorting constraint. Low prio and tough nut to crack?
 * - [ ] recreate 'hasOneThrough' relation. Low prio
 * - [ ] recreate custom relations (Marcoable?). Low prio
 * - [ ] customize recorded fields? Unnecessary unless proven otherwise
 * - [ ] configure table name? Unnecessary unless proven otherwise
 * - [ ] assess scaling issues
 *
 * TODO housekeeping
 * - [ ] cleanup the tests below and overall test setup
 * - [ ] handle test migrations properly (don't run with another driver now)
 */
class HistoryTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->prepareTables();
        config(['history.user_model' => User::class]);
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
        $this->assertNull($johnFromThePast);

        $this->assertNull(History::recreate(User::class, $john->id, Carbon::today()));
        $this->assertNull(History::recreate(User::class, $john->id, '2020-12-26'));
    }

    /** @test */
    public function soft_deleted_model()
    {
        $this->timeTravel('2020-12-01');
        $post = Post::create(['title' => 'Lazy dog']);
        $post->update(['body' => 'The quick brown fox jumps...']);

        $this->timeTravel('2020-12-03');
        $post->delete();

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-02');
        $this->assertSame('Lazy dog', $postFromThePast->title);
        $this->assertSame('The quick brown fox jumps...', $postFromThePast->body);
        $this->assertNull(History::recreate(Post::class, $post->id, '2020-12-03'));
    }

    /** @test */
    public function hard_deleted_model()
    {
        $this->timeTravel('2020-12-01');
        $post = Post::create(['title' => 'Lazy dog']);
        $post->update(['body' => 'The quick brown fox jumps...']);

        $this->timeTravel('2020-12-03');
        $post->forceDelete();

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-02');
        $this->assertSame('Lazy dog', $postFromThePast->title);
        $this->assertSame('The quick brown fox jumps...', $postFromThePast->body);
        $this->assertNull(History::recreate(Post::class, $post->id, '2020-12-03'));
    }

    /** @test */
    public function custom_user_resolvers()
    {
        $user = User::create(['name' => 'logged in user']);
        config(['history.user_resolver' => fn () => $user->id]);

        $john = User::create(['name' => 'John Doe']);
        $this->assertTrue(History::for($john)->latest()->first()->user->is($user));

        config(['history.user_resolver' => fn () => $user]);
        $john->update(['name' => 'John Delano Doe']);
        $this->assertTrue(History::for($john)->latest()->first()->user->is($user));

        config(['history.user_resolver' => fn () => new DummyAuthenticatable($user)]);

        $john->update(['name' => 'John Doe']);
        $this->assertTrue(History::for($john)->latest()->first()->user->is($user));

        config(['history.user_resolver' => fn () => (object) ['id' => $user->id]]);
        $john->update(['name' => 'John Dean Doe']);
        $this->assertTrue(History::for($john)->latest()->first()->user->is($user));
    }

    /** @test */
    public function recreates_one_to_one_relation_in_the_past()
    {
        $this->timeTravel('2020-12-01');
        $john = User::create(['name' => 'John Doe']);

        $this->timeTravel('2020-12-02');
        $post = Post::create(['title' => 'first post', 'user_id' => $john->id]);

        $this->timeTravel('2020-12-03');
        $john->update(['name' => 'John Delano Doe']);

        $this->timeTravel('2020-12-04');
        $post->update(['title' => 'first post redacted']);

        $this->timeTravel('2020-12-05');
        $john->update(['email' => 'john@example.net']);

        /** @var User $johnFromThePast */
        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-03', ['lastPost']);
        $this->assertSame('John Delano Doe', $johnFromThePast->name);
        $this->assertSame('first post', $johnFromThePast->lastPost->title);

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-04 12:00:00', ['lastPost']);
        $this->assertSame('John Delano Doe', $johnFromThePast->name);
        $this->assertSame('first post redacted', $johnFromThePast->lastPost->title);

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-04 12:00:00', ['user']);
        $this->assertSame('John Delano Doe', $postFromThePast->user->name);
        $this->assertNull($postFromThePast->user->email);
    }

    /** @test */
    public function recreates_has_one_that_changed_parent()
    {
        $this->timeTravel('2020-12-01');
        $john = User::create(['name' => 'John Doe']);
        $jane = User::create(['name' => 'jane Doe']);

        $this->timeTravel('2020-12-02');
        Post::create(['title' => 'first post by John', 'user_id' => $john->id]);
        Post::create(['title' => 'second post by John', 'user_id' => $john->id]);
        Post::create(['title' => 'first post by Jane', 'user_id' => $jane->id]);
        Post::create(['title' => 'last post by Jane', 'user_id' => $jane->id]);
        $post = Post::create(['title' => 'The Last Post', 'user_id' => $john->id]);

        $this->timeTravel('2020-12-03');
        $post->update(['user_id' => null]);

        $this->timeTravel('2020-12-04');
        $post->update(['user_id' => $jane->id]);

        $this->timeTravel('2020-12-05');
        $post->update(['user_id' => $john->id]);

        /** @var User $johnFromThePast */
        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-02', ['lastPost']);
        /** @var User $janeFromThePast */
        $janeFromThePast = History::recreate(User::class, $jane->id, '2020-12-02', ['lastPost']);
        $this->assertSame('The Last Post', $johnFromThePast->lastPost->title);
        $this->assertSame('last post by Jane', $janeFromThePast->lastPost->title);

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-03', ['lastPost']);
        $janeFromThePast = History::recreate(User::class, $jane->id, '2020-12-03', ['lastPost']);
        $this->assertSame('second post by John', $johnFromThePast->lastPost->title);
        $this->assertSame('last post by Jane', $janeFromThePast->lastPost->title);

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-04', ['lastPost']);
        $janeFromThePast = History::recreate(User::class, $jane->id, '2020-12-04', ['lastPost']);
        $this->assertSame('second post by John', $johnFromThePast->lastPost->title);
        $this->assertSame('The Last Post', $janeFromThePast->lastPost->title);

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-05', ['lastPost']);
        $janeFromThePast = History::recreate(User::class, $jane->id, '2020-12-05', ['lastPost']);
        $this->assertSame('The Last Post', $johnFromThePast->lastPost->title);
        $this->assertSame('last post by Jane', $janeFromThePast->lastPost->title);
    }

    /** @test */
    public function nothing_there_before_the_world_began()
    {
        $this->timeTravel('2020-12-01');
        $john = User::create(['name' => 'John Doe']);

        $this->assertNull(History::recreate($john, $john->id, '2020-01-01'));
    }

    /** @test */
    public function recreates_has_many_relation_from_existing_models()
    {
        $this->timeTravel('2020-12-01');
        $john = User::create(['name' => 'John Doe']);

        $this->timeTravel('2020-12-02');
        $post1 = Post::create(['title' => 'first', 'user_id' => $john->id]);
        $post2 = Post::create(['title' => 'second', 'user_id' => $john->id]);
        $post3 = Post::create(['title' => 'third', 'user_id' => $john->id]);

        $this->timeTravel('2020-12-03');
        $post1->update(['title' => 'first redacted']);
        $post2->update(['title' => 'second redacted']);
        $post3->update(['title' => 'third redacted']);
        Post::create(['title' => 'fourth', 'user_id' => $john->id]);

        $this->timeTravel('2020-12-05');
        /** @var User $johnFromThePast */
        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-02', ['posts']);
        $this->assertSame(
            ['first', 'second', 'third'],
            $johnFromThePast->posts->pluck('title')->toArray()
        );

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-04', ['posts']);
        $this->assertSame(
            ['first redacted', 'second redacted', 'third redacted', 'fourth'],
            $johnFromThePast->posts->pluck('title')->toArray()
        );
    }

    /** @test */
    public function recreates_has_many_relation_from_models_changing_owner()
    {
        $this->timeTravel('2020-12-01');
        $post1 = Post::create(['title' => 'first']);
        $post2 = Post::create(['title' => 'second']);

        $this->timeTravel('2020-12-02');
        $john = User::create(['name' => 'John Doe']);
        Post::create(['title' => 'third', 'user_id' => $john->id]);

        $this->timeTravel('2020-12-03');
        $post1->update(['user_id' => $john->id]);
        $post2->update(['user_id' => $john->id]);

        $this->timeTravel('2020-12-04');
        $post1->update(['user_id' => $john->id]);
        $post2->update(['user_id' => $john->id]);

        $this->timeTravel('2020-12-05');
        $post2->update(['user_id' => 42]);

        /** @var User $johnFromThePast */
        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-02', ['posts']);
        $this->assertSame(['third'], $johnFromThePast->posts->pluck('title')->toArray());

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-04', ['posts']);
        $this->assertSame(['first', 'second', 'third'], $johnFromThePast->posts->sortBy('id')->pluck('title')->toArray());

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-05', ['posts']);
        $this->assertSame(['first', 'third'], $johnFromThePast->posts->sortBy('id')->pluck('title')->toArray());
    }

    /** @test */
    public function recreates_belongs_to_many_after_detaching_all_at_once()
    {
        // TODO is there a way worth the effort here?
        $this->markTestSkipped('calling detach() runs single query without events, therefore it is not currently supported');

        $this->timeTravel('2020-12-01');
        $category1 = Category::create(['name' => 'first']);
        $category2 = Category::create(['name' => 'second']);
        $category3 = Category::create(['name' => 'third']);

        $this->timeTravel('2020-12-02');
        $john = User::create(['name' => 'John Doe']);
        /** @var Post $post */
        $post = Post::create(['title' => 'first', 'user_id' => $john->id]);
        $post->categories()->attach($category1);
        $post->categories()->attach($category2);
        $post->categories()->attach($category3);
        $post->update(['title' => 'first redacted']);

        $this->timeTravel('2020-12-03');
        $post->categories()->detach();

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-05', ['categories']);
        $this->assertSame([], $postFromThePast->categories->pluck('name')->toArray());
    }

    /** @test */
    public function recreates_belongs_to_many_after_detaching_specific_models()
    {
        $this->timeTravel('2020-12-01');
        $category1 = Category::create(['name' => 'first']);
        $category2 = Category::create(['name' => 'second']);
        $category3 = Category::create(['name' => 'third']);

        $this->timeTravel('2020-12-02');
        $john = User::create(['name' => 'John Doe']);
        /** @var Post $post */
        $post = Post::create(['title' => 'first', 'user_id' => $john->id]);
        $post->categories()->attach($category1);
        $post->categories()->attach($category2);
        $post->categories()->attach($category3);
        $post->update(['title' => 'first redacted']);

        $this->timeTravel('2020-12-03');
        $post->categories()->detach($category2);

        $this->timeTravel('2020-12-04');
        $post->categories()->detach($category1);

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-02', ['categories']);
        $this->assertSame(['first', 'second', 'third'], $postFromThePast->categories->pluck('name')->toArray());

        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-03', ['categories']);
        $this->assertSame(['first', 'third'], $postFromThePast->categories->pluck('name')->toArray());

        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-05', ['categories']);
        $this->assertSame(['third'], $postFromThePast->categories->pluck('name')->toArray());
    }

    /** @test */
    public function recreates_belongs_to_many_pivot_data()
    {
        $this->timeTravel('2020-12-01');
        $category1 = Category::create(['name' => 'first']);
        $category2 = Category::create(['name' => 'second']);

        $this->timeTravel('2020-12-02');
        /** @var Post $post */
        $post = Post::create();
        $post->categories()->attach($category1, ['extra_value' => 'initial']);
        $post->categories()->attach($category2, ['extra_value' => 'initial_2']);

        $this->timeTravel('2020-12-03');
        $post->categories()->sync([
            $category1->id => ['extra_value' => 'updated'],
            $category2->id => ['extra_value' => 'updated_2'],
        ]);

        $this->timeTravel('2020-12-04');
        $post->categories()->detach($category1);
        $post->categories()->updateExistingPivot($category2, ['extra_value' => 'final_2']);

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-02', ['categories']);
        $this->assertSame('initial', $postFromThePast->categories->find($category1->id)->pivot->extra_value);

        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-03', ['categories']);
        $this->assertSame('updated', $postFromThePast->categories->find($category1->id)->pivot->extra_value);
        $this->assertSame('updated_2', $postFromThePast->categories->find($category2->id)->pivot->extra_value);

        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-04', ['categories']);
        $this->assertNull($postFromThePast->categories->find($category1));
        $this->assertSame('final_2', $postFromThePast->categories->find($category2->id)->pivot->extra_value);

        /** @var Category $categoryFromThePast */
        $categoryFromThePast = History::recreate(Category::class, $category2->id, '2020-12-04', ['posts']);
        $this->assertSame('final_2', $categoryFromThePast->posts->find($post->id)->pivot->extra_value);
    }

    /** @test */
    public function recreates_has_many_models_after_they_were_deleted()
    {
        $this->timeTravel('2020-12-01');
        /** @var User $john */
        $john = User::create();
        $post1 = Post::create(['user_id' => $john->id]);

        $this->timeTravel('2020-12-02');
        $post2 = Post::create(['user_id' => $john->id]);

        $this->timeTravel('2020-12-03');
        $post1->delete();

        $this->timeTravel('2020-12-04');
        $post2->forceDelete();

        /** @var User $johnFromThePast */
        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-02', ['posts']);
        $this->assertSame(2, $johnFromThePast->posts->count());
        $this->assertFalse(Post::exists());

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-03', ['posts']);
        $this->assertSame(1, $johnFromThePast->posts->count());

        $johnFromThePast = History::recreate(User::class, $john->id, '2020-12-04', ['posts']);
        $this->assertSame(0, $johnFromThePast->posts->count());
    }

    /** @test */
    public function recreates_belongs_to_many_models_after_they_were_deleted()
    {
        $this->timeTravel('2020-12-01');
        /** @var Category $category */
        $category = Category::create();
        /** @var Post $post */
        $post = Post::create();

        $this->timeTravel('2020-12-02');
        $post->categories()->attach($category, ['extra_value' => 'extra_initial']);
        $post->categories()->sync([$category->id => ['extra_value' => 'extra_updated']]);

        $this->timeTravel('2020-12-03');
        $post->categories()->detach($category);

        $this->timeTravel('2020-12-04');
        $category->forceDelete();

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-02', ['categories']);
        $this->assertSame('extra_updated', $postFromThePast->categories->find($category->id)->pivot->extra_value);
    }

    /** @test */
    public function recreates_morph_to_relations()
    {
        $this->timeTravel('2020-12-01');
        $post1 = Post::create(['title' => 'first post']);
        $post2 = Post::create(['title' => 'second post']);
        $category = Category::create();

        $this->timeTravel('2020-12-02');
        $comment = $post1->comments()->create();

        $this->timeTravel('2020-12-03');
        $post2->comments()->save($comment);

        $this->timeTravel('2020-12-04');
        $post1->comments()->save($comment);

        $this->timeTravel('2020-12-05');
        $category->comments()->save($comment);

        /** @var Comment $commentFromThePast */
        $commentFromThePast = History::recreate(Comment::class, $comment->uuid, '2020-12-02', ['model']);
        $this->assertSame($commentFromThePast->model->getKey(), $post1->getKey());
        $this->assertTrue($commentFromThePast->model instanceof Post);

        $commentFromThePast = History::recreate(Comment::class, $comment->uuid, '2020-12-03', ['model']);
        $this->assertSame($commentFromThePast->model->getKey(), $post2->getKey());
        $this->assertTrue($commentFromThePast->model instanceof Post);

        $commentFromThePast = History::recreate(Comment::class, $comment->uuid, '2020-12-04', ['model']);
        $this->assertSame($commentFromThePast->model->getKey(), $post1->getKey());
        $this->assertTrue($commentFromThePast->model instanceof Post);

        $commentFromThePast = History::recreate(Comment::class, $comment->uuid, '2020-12-05', ['model']);
        $this->assertSame($commentFromThePast->model->getKey(), $category->getKey());
        $this->assertTrue($commentFromThePast->model instanceof Category);
    }

    /** @test */
    public function recreates_morph_many_relations()
    {
        $this->timeTravel('2020-12-01');
        $post1 = Post::create();
        $post2 = Post::create();
        $comment1_1 = $post1->comments()->create();

        $this->timeTravel('2020-12-02');
        $comment1_2 = $post1->comments()->create();
        $comment1_3 = $post1->comments()->create();
        $comment2_1 = $post2->comments()->create();

        $this->timeTravel('2020-12-03');
        $post2->comments()->save($comment1_1);

        /** @var Post $post1FromThePast */
        $post1FromThePast = History::recreate(Post::class, $post1->id, '2020-12-01', ['comments']);
        $this->assertEquals(collect([$comment1_1->uuid]), $post1FromThePast->comments->pluck('uuid'));

        $post1FromThePast = History::recreate(Post::class, $post1->id, '2020-12-02', ['comments']);
        $this->assertEquals(
            collect([$comment1_1, $comment1_2, $comment1_3])->pluck('uuid')->sort()->values(),
            $post1FromThePast->comments->pluck('uuid')->sort()->values(),
        );

        $post1FromThePast = History::recreate(Post::class, $post1->id, '2020-12-03', ['comments']);
        $this->assertEquals(
            collect([$comment1_2, $comment1_3])->pluck('uuid')->sort()->values(),
            $post1FromThePast->comments->pluck('uuid')->sort()->values(),
        );

        /** @var Post $post2FromThePast */
        $post2FromThePast = History::recreate(Post::class, $post2->id, '2020-12-03', ['comments']);
        $this->assertEquals(
            collect([$comment1_1, $comment2_1])->pluck('uuid')->sort()->values(),
            $post2FromThePast->comments->pluck('uuid')->sort()->values(),
        );
    }

    /** @test */
    public function recreates_morph_to_many_relations()
    {
        $this->timeTravel('2020-12-01');
        $post = Post::create();
        $category = Category::create();
        $fancy = Tag::create(['name' => 'fancy']);
        $funky = Tag::create(['name' => 'funky']);
        $post->tags()->attach($fancy);

        $this->timeTravel('2020-12-02');
        $category->tags()->attach($funky);

        $this->timeTravel('2020-12-03');
        $category->tags()->detach();

        $this->timeTravel('2020-12-04');
        $post->tags()->sync([$funky->id, $fancy->id]);
        $category->tags()->attach($fancy);

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-01', ['tags']);
        $this->assertEquals([$fancy->id], $postFromThePast->tags->pluck('id')->toArray());

        /** @var Category $categoryFromThePast */
        $categoryFromThePast = History::recreate(Category::class, $category->id, '2020-12-02', ['tags']);
        $this->assertEquals([$funky->id], $categoryFromThePast->tags->pluck('id')->toArray());

        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-05', ['tags']);
        $categoryFromThePast = History::recreate(Category::class, $category->id, '2020-12-05', ['tags']);
        $this->assertEquals([$fancy->id, $funky->id], $categoryFromThePast->tags->pluck('id')->sort()->values()->toArray());
        $this->assertEquals([$fancy->id, $funky->id], $postFromThePast->tags->pluck('id')->sort()->values()->toArray());
    }

    /** @test */
    public function recreates_morph_pivot_relations_both_ways()
    {
        $this->timeTravel('2020-12-01');
        /** @var Category $category */
        $category = Category::create();
        /** @var Post $post */
        $post = Post::create();

        $fancy = Tag::create(['name' => 'fancy']);
        $funky = Tag::create(['name' => 'funky']);
        $funny = Tag::create(['name' => 'funny']);
        $fancy->posts()->attach($post);
        $funky->posts()->attach($post);
        $funny->categories()->sync($category);

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-01', ['tags']);
        $this->assertEquals([$fancy->id, $funky->id], $postFromThePast->tags->pluck('id')->sort()->values()->toArray());
    }

    /** @test */
    public function recreates_has_through_relations()
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped(
                'HasManyThrough history depends on WHERE IN on JSON column, which is not supported in sqlite. Use different DB driver to run this test.'
            );
        }

        $this->timeTravel('2020-12-01');
        /** @var User $category */
        $john = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.net',
        ]);
        $post1 = Post::create(['user_id' => $john->id]);
        $post2 = Post::create(['user_id' => $john->id]);
        $post3 = Post::create(['user_id' => null]);

        $this->timeTravel('2020-12-02');
        Version::create(['post_id' => $post1->id, 'version' => 1]);

        $this->timeTravel('2020-12-03');
        Version::create(['post_id' => $post2->id, 'version' => 1]);

        $this->timeTravel('2020-12-04');
        $version = Version::create(['post_id' => $post1->id, 'version' => 2]);

        $this->timeTravel('2020-12-05');
        $version->update(['post_id' => $post3->id]);

        /** @var User $johnFromThePast */
        $johnFromThePast = User::recreate($john->id, '2020-12-01', ['postVersions']);
        $this->assertEquals([], $johnFromThePast->postVersions->groupBy('post_id')->map->pluck('version')->toArray());

        $johnFromThePast = User::recreate($john->id, '2020-12-04', ['postVersions']);
        $this->assertEquals([
            $post1->id => [1, 2],
            $post2->id => [1],
        ], $johnFromThePast->postVersions->groupBy('post_id')->map->pluck('version')->toArray());

        $johnFromThePast = User::recreate($john->id, '2020-12-05', ['postVersions']);
        $this->assertEquals([
            $post1->id => [1],
            $post2->id => [1],
        ], $johnFromThePast->postVersions->groupBy('post_id')->map->pluck('version')->toArray());
    }

    /** @test */
    public function adds_method_macro_to_all_models()
    {
        $this->timeTravel('2020-12-01');
        /** @var Category $category */
        $category = Category::create();
        /** @var Post $post */
        $post = Post::create();

        $fancy = Tag::create(['name' => 'fancy']);
        $funky = Tag::create(['name' => 'funky']);
        $funny = Tag::create(['name' => 'funny']);
        $fancy->posts()->attach($post);
        $funky->posts()->attach($post);
        $funny->categories()->sync($category);

        /** @var Post $postFromThePast */
        $postFromThePast = History::recreate(Post::class, $post->id, '2020-12-01', ['tags']);
        $this->assertEquals($postFromThePast, Post::recreate($post->id, '2020-12-01', ['tags']));
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
            $table->bigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('category_post', function (Blueprint $table) {
            $table->bigInteger('category_id');
            $table->bigInteger('post_id');
            $table->string('extra_value')->nullable();
            $table->string('another_value')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('uuid');
            $table->string('body')->nullable();
            $table->morphs('model');
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->unsignedBigInteger('tag_id');
            $table->morphs('taggable');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('post_id');
            $table->integer('version');
            $table->timestamps();
        });
    }
}
