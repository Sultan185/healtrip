<?php

namespace Database\Seeders;

use App\Enums\Specialty;
use App\Models\Doctor;
use App\Models\Hospital;
use Illuminate\Database\Seeder;

/**
 * The mock provider directory. Every hospital, doctor, address and phone
 * number below is fictional and exists only to give the agent something
 * real to search.
 */
class DirectorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->hospitals() as $key => $data) {
            $specialties = $data['specialties'];
            unset($data['specialties']);

            $hospital = Hospital::updateOrCreate(['name_en' => $data['name_en']], $data);
            $hospital->syncSpecialties($specialties);

            foreach ($this->doctors()[$key] as $doctor) {
                [$nameEn, $nameAr, $specialty, $focusEn, $focusAr, $years, $languages, $fee, $secondOpinion, $telemedicine, $rating, $availableInDays] = $doctor;

                Doctor::updateOrCreate(
                    ['name_en' => $nameEn, 'hospital_id' => $hospital->id],
                    [
                        'name_ar' => $nameAr,
                        'specialty' => $specialty,
                        'focus_en' => $focusEn,
                        'focus_ar' => $focusAr,
                        'years_experience' => $years,
                        'languages' => $languages,
                        'consultation_fee_usd' => $fee,
                        'offers_second_opinion' => $secondOpinion,
                        'offers_telemedicine' => $telemedicine,
                        'rating' => $rating,
                        'next_available_on' => today()->addDays($availableInDays),
                    ],
                );
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function hospitals(): array
    {
        return [
            'bosphorus' => [
                'name_en' => 'Bosphorus Heart & Vascular Institute',
                'name_ar' => 'معهد البوسفور للقلب والأوعية الدموية',
                'country_code' => 'TR',
                'city_en' => 'Istanbul',
                'city_ar' => 'إسطنبول',
                'address_en' => 'Büyükdere Cd. 120, Levent, Beşiktaş',
                'phone' => '+90 212 555 01 10',
                'has_emergency' => true,
                'emergency_phone' => '+90 212 555 01 11',
                'accreditation' => 'JCI',
                'languages' => ['tr', 'en', 'ar'],
                'international_patient_desk' => true,
                'specialties' => [Specialty::Cardiology, Specialty::CardiacSurgery, Specialty::EmergencyMedicine, Specialty::InternalMedicine],
            ],
            'anatolia' => [
                'name_en' => 'Anatolia International Hospital',
                'name_ar' => 'مستشفى الأناضول الدولي',
                'country_code' => 'TR',
                'city_en' => 'Istanbul',
                'city_ar' => 'إسطنبول',
                'address_en' => 'Ataşehir Blv. 45, Ataşehir',
                'phone' => '+90 216 555 02 20',
                'has_emergency' => true,
                'emergency_phone' => '+90 216 555 02 21',
                'accreditation' => 'JCI',
                'languages' => ['tr', 'en', 'ar', 'de'],
                'international_patient_desk' => true,
                'specialties' => [Specialty::Orthopedics, Specialty::Neurology, Specialty::Oncology, Specialty::Gastroenterology, Specialty::InternalMedicine, Specialty::EmergencyMedicine],
            ],
            'ankara' => [
                'name_en' => 'Ankara Capital Medical Center',
                'name_ar' => 'مركز أنقرة كابيتال الطبي',
                'country_code' => 'TR',
                'city_en' => 'Ankara',
                'city_ar' => 'أنقرة',
                'address_en' => 'Çankaya Cd. 78, Çankaya',
                'phone' => '+90 312 555 03 30',
                'has_emergency' => true,
                'emergency_phone' => '+90 312 555 03 31',
                'accreditation' => null,
                'languages' => ['tr', 'en'],
                'international_patient_desk' => false,
                'specialties' => [Specialty::Cardiology, Specialty::Neurology, Specialty::InternalMedicine, Specialty::EmergencyMedicine],
            ],
            'gulf' => [
                'name_en' => 'Gulf Crescent Specialty Hospital',
                'name_ar' => 'مستشفى هلال الخليج التخصصي',
                'country_code' => 'AE',
                'city_en' => 'Dubai',
                'city_ar' => 'دبي',
                'address_en' => 'Al Wasl Road 210, Jumeirah',
                'phone' => '+971 4 555 0440',
                'has_emergency' => true,
                'emergency_phone' => '+971 4 555 0441',
                'accreditation' => 'JCI',
                'languages' => ['ar', 'en', 'fr'],
                'international_patient_desk' => true,
                'specialties' => [Specialty::Cardiology, Specialty::Orthopedics, Specialty::Oncology, Specialty::Gastroenterology, Specialty::EmergencyMedicine],
            ],
            'marina' => [
                'name_en' => 'Marina Bay Outpatient Clinic',
                'name_ar' => 'عيادات مارينا باي الخارجية',
                'country_code' => 'AE',
                'city_en' => 'Dubai',
                'city_ar' => 'دبي',
                'address_en' => 'Marina Walk, Tower B, Dubai Marina',
                'phone' => '+971 4 555 0550',
                'has_emergency' => false,
                'emergency_phone' => null,
                'accreditation' => null,
                'languages' => ['ar', 'en'],
                'international_patient_desk' => false,
                'specialties' => [Specialty::Cardiology, Specialty::InternalMedicine, Specialty::Gastroenterology],
            ],
            'nile' => [
                'name_en' => 'Nile Valley Heart Center',
                'name_ar' => 'مركز وادي النيل للقلب',
                'country_code' => 'EG',
                'city_en' => 'Cairo',
                'city_ar' => 'القاهرة',
                'address_en' => '90th Street, Fifth Settlement, New Cairo',
                'phone' => '+20 2 5550 0660',
                'has_emergency' => true,
                'emergency_phone' => '+20 2 5550 0661',
                'accreditation' => 'GAHAR',
                'languages' => ['ar', 'en'],
                'international_patient_desk' => true,
                'specialties' => [Specialty::Cardiology, Specialty::CardiacSurgery, Specialty::EmergencyMedicine],
            ],
            'cairo' => [
                'name_en' => 'Cairo International Medical City',
                'name_ar' => 'مدينة القاهرة الطبية الدولية',
                'country_code' => 'EG',
                'city_en' => 'Cairo',
                'city_ar' => 'القاهرة',
                'address_en' => 'Ring Road, Maadi',
                'phone' => '+20 2 5550 0770',
                'has_emergency' => true,
                'emergency_phone' => '+20 2 5550 0771',
                'accreditation' => 'JCI',
                'languages' => ['ar', 'en', 'fr'],
                'international_patient_desk' => true,
                'specialties' => [Specialty::Oncology, Specialty::Neurology, Specialty::Orthopedics, Specialty::Gastroenterology, Specialty::InternalMedicine, Specialty::EmergencyMedicine],
            ],
            'najd' => [
                'name_en' => 'Najd Specialist Hospital',
                'name_ar' => 'مستشفى نجد التخصصي',
                'country_code' => 'SA',
                'city_en' => 'Riyadh',
                'city_ar' => 'الرياض',
                'address_en' => 'King Fahd Road, Al Olaya',
                'phone' => '+966 11 555 0880',
                'has_emergency' => true,
                'emergency_phone' => '+966 11 555 0881',
                'accreditation' => 'CBAHI',
                'languages' => ['ar', 'en'],
                'international_patient_desk' => true,
                'specialties' => [Specialty::Cardiology, Specialty::InternalMedicine, Specialty::Orthopedics, Specialty::EmergencyMedicine],
            ],
            'rhein' => [
                'name_en' => 'Rhein-Main University Clinic',
                'name_ar' => 'مستشفى راين-ماين الجامعي',
                'country_code' => 'DE',
                'city_en' => 'Frankfurt',
                'city_ar' => 'فرانكفورت',
                'address_en' => 'Theodor-Stern-Kai 7',
                'phone' => '+49 69 5550 0990',
                'has_emergency' => true,
                'emergency_phone' => '+49 69 5550 0991',
                'accreditation' => 'JCI',
                'languages' => ['de', 'en', 'ar'],
                'international_patient_desk' => true,
                'specialties' => [Specialty::Cardiology, Specialty::CardiacSurgery, Specialty::Oncology, Specialty::Neurology, Specialty::EmergencyMedicine],
            ],
        ];
    }

    /**
     * Columns: name_en, name_ar, specialty, focus_en, focus_ar, years of
     * experience, languages, fee (USD), second opinion, telemedicine, rating,
     * days until next availability.
     *
     * @return array<string, list<array{string, string, Specialty, string, string, int, list<string>, int, bool, bool, float, int}>>
     */
    private function doctors(): array
    {
        return [
            'bosphorus' => [
                ['Elif Kaya', 'إليف كايا', Specialty::Cardiology, 'Interventional cardiology and chest pain evaluation', 'القسطرة التداخلية وتقييم آلام الصدر', 18, ['tr', 'en'], 180, true, true, 4.9, 2],
                ['Kerem Yildiz', 'كرم يلدز', Specialty::Cardiology, 'Heart rhythm disorders (electrophysiology)', 'اضطرابات نظم القلب', 13, ['tr', 'en', 'ar'], 160, true, true, 4.7, 4],
                ['Murat Demir', 'مراد دمير', Specialty::CardiacSurgery, 'Coronary bypass and valve surgery', 'جراحات الشرايين التاجية والصمامات', 24, ['tr', 'en', 'ar'], 250, true, false, 4.8, 6],
                ['Selin Arslan', 'سيلين أرسلان', Specialty::EmergencyMedicine, 'Acute cardiac emergencies', 'طوارئ القلب الحادة', 11, ['tr', 'en'], 120, false, false, 4.6, 0],
            ],
            'anatolia' => [
                ['Deniz Aksoy', 'دينيز أكسوي', Specialty::Orthopedics, 'Knee and hip replacement', 'استبدال مفصل الركبة والورك', 20, ['tr', 'en', 'de'], 170, true, true, 4.8, 5],
                ['Zeynep Sahin', 'زينب شاهين', Specialty::Neurology, 'Stroke and headache disorders', 'السكتة الدماغية واضطرابات الصداع', 15, ['tr', 'en', 'ar'], 150, true, true, 4.7, 3],
                ['Baran Ozturk', 'باران أوزتورك', Specialty::Oncology, 'Breast and gastrointestinal cancers', 'أورام الثدي والجهاز الهضمي', 22, ['tr', 'en'], 220, true, true, 4.9, 7],
                ['Aylin Celik', 'أيلين تشيليك', Specialty::Gastroenterology, 'Endoscopy and reflux disease', 'المناظير وارتجاع المريء', 12, ['tr', 'en', 'ar'], 140, false, true, 4.5, 2],
            ],
            'ankara' => [
                ['Hakan Polat', 'هاكان بولات', Specialty::Cardiology, 'Preventive cardiology and hypertension', 'الوقاية القلبية وارتفاع ضغط الدم', 16, ['tr', 'en'], 110, false, true, 4.5, 1],
                ['Ece Korkmaz', 'إيجه كوركماز', Specialty::InternalMedicine, 'General check-ups and chronic disease follow-up', 'الفحوصات العامة ومتابعة الأمراض المزمنة', 9, ['tr', 'en'], 80, false, true, 4.4, 1],
            ],
            'gulf' => [
                ['Omar Al-Mansouri', 'عمر المنصوري', Specialty::Cardiology, 'Coronary artery disease and cardiac imaging', 'أمراض الشرايين التاجية وتصوير القلب', 19, ['ar', 'en'], 260, true, true, 4.8, 3],
                ['Layla Haddad', 'ليلى حداد', Specialty::Oncology, 'Medical oncology and second-opinion reviews', 'علاج الأورام ومراجعات الرأي الثاني', 17, ['ar', 'en', 'fr'], 300, true, true, 4.9, 8],
                ['Faisal Al-Nuaimi', 'فيصل النعيمي', Specialty::Orthopedics, 'Sports injuries and arthroscopy', 'إصابات الملاعب ومناظير المفاصل', 14, ['ar', 'en'], 230, false, false, 4.6, 4],
                ['Rania Saleh', 'رانيا صالح', Specialty::EmergencyMedicine, 'Emergency and acute care', 'الطوارئ والرعاية الحادة', 10, ['ar', 'en'], 150, false, false, 4.5, 0],
            ],
            'marina' => [
                ['Yousef Darwish', 'يوسف درويش', Specialty::Cardiology, 'Outpatient cardiology and heart check-ups', 'عيادات القلب والفحوصات الدورية', 12, ['ar', 'en'], 200, false, true, 4.4, 1],
                ['Hana Al-Zaabi', 'هنا الزعابي', Specialty::InternalMedicine, 'Family and internal medicine', 'طب الأسرة والباطنة', 8, ['ar', 'en'], 120, false, true, 4.6, 0],
            ],
            'nile' => [
                ['Ahmed El-Sharkawy', 'أحمد الشرقاوي', Specialty::Cardiology, 'Interventional cardiology and cardiac catheterization', 'القسطرة القلبية التداخلية', 21, ['ar', 'en'], 60, true, true, 4.8, 2],
                ['Mona Abdel-Rahman', 'منى عبد الرحمن', Specialty::Cardiology, 'Heart failure and women\'s heart health', 'هبوط القلب وصحة قلب المرأة', 14, ['ar', 'en', 'fr'], 50, true, true, 4.7, 3],
                ['Tarek Mansour', 'طارق منصور', Specialty::CardiacSurgery, 'Minimally invasive cardiac surgery', 'جراحات القلب بالمنظار', 23, ['ar', 'en'], 90, true, false, 4.9, 9],
                ['Sherif Nabil', 'شريف نبيل', Specialty::EmergencyMedicine, 'Chest pain unit and emergency care', 'وحدة آلام الصدر والطوارئ', 12, ['ar', 'en'], 35, false, false, 4.5, 0],
            ],
            'cairo' => [
                ['Nadia Fouad', 'نادية فؤاد', Specialty::Oncology, 'Hematology and lymphoma', 'أمراض الدم والأورام الليمفاوية', 18, ['ar', 'en', 'fr'], 70, true, true, 4.8, 5],
                ['Karim Hosny', 'كريم حسني', Specialty::Neurology, 'Epilepsy and movement disorders', 'الصرع واضطرابات الحركة', 13, ['ar', 'en'], 55, true, true, 4.6, 2],
                ['Yasmin El-Gendy', 'ياسمين الجندي', Specialty::Orthopedics, 'Spine and back pain', 'العمود الفقري وآلام الظهر', 16, ['ar', 'en'], 60, true, false, 4.7, 4],
                ['Hossam Zaki', 'حسام زكي', Specialty::Gastroenterology, 'Liver disease and endoscopy', 'أمراض الكبد والمناظير', 15, ['ar', 'en'], 50, false, true, 4.5, 3],
            ],
            'najd' => [
                ['Abdullah Al-Qahtani', 'عبدالله القحطاني', Specialty::Cardiology, 'General cardiology and echocardiography', 'أمراض القلب العامة وإيكو القلب', 17, ['ar', 'en'], 190, true, true, 4.7, 3],
                ['Noura Al-Harbi', 'نورة الحربي', Specialty::InternalMedicine, 'Diabetes and internal medicine', 'السكري والباطنة', 11, ['ar', 'en'], 130, false, true, 4.6, 1],
                ['Saad Al-Otaibi', 'سعد العتيبي', Specialty::Orthopedics, 'Trauma and fracture care', 'الإصابات والكسور', 15, ['ar', 'en'], 170, false, false, 4.5, 2],
            ],
            'rhein' => [
                ['Katharina Vogel', 'كاتارينا فوغل', Specialty::Cardiology, 'Complex coronary disease and second-opinion programme', 'أمراض الشرايين التاجية المعقدة وبرنامج الرأي الثاني', 25, ['de', 'en'], 420, true, true, 4.9, 10],
                ['Stefan Brandt', 'شتيفان برانت', Specialty::CardiacSurgery, 'Aortic and valve surgery', 'جراحات الأبهر والصمامات', 27, ['de', 'en'], 480, true, true, 4.9, 14],
                ['Miriam Hoffmann', 'ميريام هوفمان', Specialty::Oncology, 'Lung and thoracic oncology', 'أورام الرئة والصدر', 19, ['de', 'en', 'ar'], 450, true, true, 4.8, 12],
                ['Jonas Weber', 'يوناس فيبر', Specialty::Neurology, 'Multiple sclerosis and neuro-immunology', 'التصلب المتعدد والمناعة العصبية', 16, ['de', 'en'], 400, true, true, 4.7, 9],
            ],
        ];
    }
}
