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

        $jairo = User::factory()
            ->has(Post::factory(45))
            ->has(Comment::factory(120)->recycle($posts))
            ->create([
                'email' => 'jairosarabia@gmail.com',
                'password' => 'my32Forum847@'
            ]);
    }
}
