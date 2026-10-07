<?php

// Settings → Activity (NFR-S.5): record types and field names of the audit log.

return [

    'records' => [
        'patient' => 'المريض',
        'history_entry' => 'بند في التاريخ المرضي',
        'visit' => 'الزيارة',
        'payment' => 'دفعة',
        'prescription' => 'الروشتة',
        'prescription_item' => 'سطر في الروشتة',
    ],

    'fields' => [
        'address' => 'العنوان',
        'amount' => 'المبلغ',
        'appointment_id' => 'الموعد',
        'current_illness' => 'الشكوى الحالية',
        'date_of_birth' => 'تاريخ الميلاد',
        'details' => 'التفاصيل',
        'doctor_id' => 'الطبيب',
        'drug_form' => 'شكل الدواء',
        'drug_id' => 'الدواء',
        'drug_name' => 'اسم الدواء',
        'gender' => 'النوع',
        'instructions' => 'طريقة الاستخدام',
        'issued_on' => 'التاريخ',
        'method' => 'طريقة الدفع',
        'notes' => 'ملاحظات',
        'paid_at' => 'وقت الدفع',
        'patient_id' => 'المريض',
        'patient_visible' => 'ظاهر للمريض',
        'position' => 'ترتيب السطر',
        'prescription_id' => 'الروشتة',
        'recorded_by' => 'سجّلها',
        'recorded_on' => 'التاريخ',
        'title' => 'العنوان',
        'total_amount' => 'التكلفة الإجمالية',
        'type' => 'النوع',
        'user_id' => 'الحساب',
        'visit_date' => 'تاريخ الزيارة',
        'visit_id' => 'الزيارة',
        'work_done' => 'العمل المنجز',
    ],

];
