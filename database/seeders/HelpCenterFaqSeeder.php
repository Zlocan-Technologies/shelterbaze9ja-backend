<?php

namespace Database\Seeders;

use App\Models\HelpCenterFaq;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HelpCenterFaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                "question" => "Setup profile for the first time",
                "answer" => "To set up your profile for the first time, navigate to the profile section in your account settings. Fill in all the required fields such as your name, contact information, and any other relevant details. Make sure to save your changes before exiting the page."
            ],
            [
                "question" => "Upload profile picture",
                "answer" => "To upload a profile picture, go to your profile settings and click on the 'Change Profile Picture' button. Select an image file from your device and upload it. Make sure the image meets the required specifications."
            ]
        ];

        foreach ($faqs as $faq) {
            HelpCenterFaq::firstOrCreate($faq);
        }
    }
}
