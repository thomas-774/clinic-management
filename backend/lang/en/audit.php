<?php

// Settings → Activity (NFR-S.5): record types and field names of the audit log.

return [

    'records' => [
        'patient' => 'Patient',
        'history_entry' => 'History entry',
        'visit' => 'Visit',
        'payment' => 'Payment',
        'prescription' => 'Prescription',
        'prescription_item' => 'Prescription line',
    ],

    'fields' => [
        'address' => 'Address',
        'amount' => 'Amount',
        'appointment_id' => 'Appointment',
        'current_illness' => 'Current illness',
        'date_of_birth' => 'Date of birth',
        'details' => 'Details',
        'doctor_id' => 'Doctor',
        'drug_form' => 'Drug form',
        'drug_id' => 'Drug',
        'drug_name' => 'Drug name',
        'gender' => 'Gender',
        'instructions' => 'Instructions',
        'issued_on' => 'Date',
        'method' => 'Payment method',
        'notes' => 'Notes',
        'paid_at' => 'Paid at',
        'patient_id' => 'Patient',
        'patient_visible' => 'Shown to the patient',
        'position' => 'Line order',
        'prescription_id' => 'Prescription',
        'recorded_by' => 'Recorded by',
        'recorded_on' => 'Date',
        'title' => 'Title',
        'total_amount' => 'Total cost',
        'type' => 'Type',
        'user_id' => 'Account',
        'visit_date' => 'Visit date',
        'visit_id' => 'Visit',
        'work_done' => 'Work done',
    ],

];
