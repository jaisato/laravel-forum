<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Factories\PostFactory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = User::factory(10)
            ->create();

        $posts = Post::factory(200)
            ->recycle($users)
            ->create();

        $comments = Comment::factory(100)
            ->recycle($users)
            ->recycle($posts)
            ->create();

        // A known account to log in with on a freshly seeded database. These
        // are development defaults, not anybody's real credentials, and both
        // can be overridden from the environment (SEED_USER_EMAIL and
        // SEED_USER_PASSWORD) without editing this file. The password is
        // hashed by the model's cast, as with every other user.
        User::factory()
            ->has(Post::factory(45))
            ->has(Comment::factory(120)->recycle($posts))
            ->create([
                'email' => env('SEED_USER_EMAIL', 'admin@example.com'),
                'password' => env('SEED_USER_PASSWORD', 'password'),
            ]);
    }
}
