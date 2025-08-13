<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatesAndLgasSeeder extends Seeder
{
    public function run()
    {
        // You can create a states_and_lgas table or use this data for validation
        $statesAndLgas = [
            'Lagos' => [
                'Agege', 'Ajeromi-Ifelodun', 'Alimosho', 'Amuwo-Odofin', 'Apapa',
                'Badagry', 'Epe', 'Eti-Osa', 'Ibeju-Lekki', 'Ifako-Ijaiye',
                'Ikeja', 'Ikorodu', 'Kosofe', 'Lagos Island', 'Lagos Mainland',
                'Mushin', 'Ojo', 'Oshodi-Isolo', 'Shomolu', 'Surulere'
            ],
            'Abuja' => [
                'Abaji', 'Abuja Municipal', 'Bwari', 'Gwagwalada', 'Kuje', 'Kwali'
            ],
            'Ogun' => [
                'Abeokuta North', 'Abeokuta South', 'Ado-Odo/Ota', 'Ewekoro',
                'Ifo', 'Ijebu East', 'Ijebu North', 'Ijebu North East',
                'Ijebu Ode', 'Ikenne', 'Imeko Afon', 'Ipokia', 'Obafemi Owode',
                'Odeda', 'Odogbolu', 'Ogun Waterside', 'Remo North', 'Sagamu',
                'Yewa North', 'Yewa South'
            ],
            // Add more states as needed
        ];

        // You can insert this data into a states_lgas table if needed
        // Or use it for validation in your application
    }
}