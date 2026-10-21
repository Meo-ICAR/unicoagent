<?php

namespace Database\Factories;

use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConversationMessage> */
class ConversationMessageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'wa_number' => fake()->numerify('39333#######'),
            'direction' => fake()->randomElement([ConversationMessage::IN, ConversationMessage::OUT]),
            'body' => fake()->sentence(),
        ];
    }
}
