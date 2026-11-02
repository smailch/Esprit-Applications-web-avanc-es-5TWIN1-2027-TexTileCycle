<?php

namespace Database\Factories;

use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Usage : Message::factory()->for($conversation)->create(['user_id' => …, 'cote' => 'admin']);
 *
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => fn () => (string) Conversation::factory()->create()->getKey(),
            'user_id' => fn (array $attributes) => (string) Conversation::find($attributes['conversation_id'])?->admin_id,
            'cote' => Conversation::COTE_ADMIN,
            'contenu' => $this->faker->sentence(12),
        ];
    }
}
