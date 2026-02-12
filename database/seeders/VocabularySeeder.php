<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VocabList;
use App\Models\VocabItem;
use Illuminate\Database\Seeder;

class VocabularySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstWhere('is_admin', true);
        if (!$admin) {
            return;
        }

        $list = VocabList::create([
            'title' => 'Daily Life Essentials',
            'level' => 'A2',
            'description' => 'Common words for daily routines and situations.',
            'created_by' => $admin->id,
        ]);

        $items = [
            ['term' => 'appointment', 'definition' => 'a meeting arranged for a specific time', 'example' => 'I have a doctor appointment at 3 PM.', 'part_of_speech' => 'noun'],
            ['term' => 'commute', 'definition' => 'to travel regularly between home and work', 'example' => 'She commutes by train every day.', 'part_of_speech' => 'verb'],
            ['term' => 'routine', 'definition' => 'a regular way of doing things', 'example' => 'My morning routine starts with coffee.', 'part_of_speech' => 'noun'],
            ['term' => 'grocery', 'definition' => 'food and household items', 'example' => 'We need to buy groceries today.', 'part_of_speech' => 'noun'],
            ['term' => 'laundry', 'definition' => 'clothes that need to be washed', 'example' => 'I do laundry on Saturdays.', 'part_of_speech' => 'noun'],
            ['term' => 'schedule', 'definition' => 'a plan for when things will happen', 'example' => 'My schedule is busy this week.', 'part_of_speech' => 'noun'],
            ['term' => 'confirm', 'definition' => 'to say something is true or certain', 'example' => 'Please confirm your email address.', 'part_of_speech' => 'verb'],
            ['term' => 'cancel', 'definition' => 'to decide that something will not happen', 'example' => 'They canceled the meeting.', 'part_of_speech' => 'verb'],
            ['term' => 'reschedule', 'definition' => 'to arrange a new time', 'example' => 'Can we reschedule for tomorrow?', 'part_of_speech' => 'verb'],
            ['term' => 'reminder', 'definition' => 'something that helps you remember', 'example' => 'Set a reminder on your phone.', 'part_of_speech' => 'noun'],
            ['term' => 'queue', 'definition' => 'a line of people waiting', 'example' => 'There was a long queue at the bank.', 'part_of_speech' => 'noun'],
            ['term' => 'receipt', 'definition' => 'a paper that shows you paid', 'example' => 'Keep your receipt for returns.', 'part_of_speech' => 'noun'],
            ['term' => 'refund', 'definition' => 'money returned for a purchase', 'example' => 'I got a refund for the ticket.', 'part_of_speech' => 'noun'],
            ['term' => 'delivery', 'definition' => 'bringing goods to a place', 'example' => 'The delivery arrived this morning.', 'part_of_speech' => 'noun'],
            ['term' => 'available', 'definition' => 'able to be used or obtained', 'example' => 'This item is available online.', 'part_of_speech' => 'adjective'],
            ['term' => 'repair', 'definition' => 'to fix something', 'example' => 'They repaired the broken chair.', 'part_of_speech' => 'verb'],
            ['term' => 'borrow', 'definition' => 'to take and use with intent to return', 'example' => 'Can I borrow your pen?', 'part_of_speech' => 'verb'],
            ['term' => 'return', 'definition' => 'to give back', 'example' => 'Please return the book next week.', 'part_of_speech' => 'verb'],
            ['term' => 'expense', 'definition' => 'money spent on something', 'example' => 'Rent is my biggest expense.', 'part_of_speech' => 'noun'],
            ['term' => 'balance', 'definition' => 'the amount of money in an account', 'example' => 'Check your bank balance.', 'part_of_speech' => 'noun'],
        ];

        foreach ($items as $item) {
            VocabItem::create($item + ['vocab_list_id' => $list->id]);
        }
    }
}
