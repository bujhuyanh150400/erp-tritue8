import json
import os
import datetime

tables = [
    ("users", [
        "$table->id();",
        "$table->string('username', 50)->unique();",
        "$table->string('password', 255);",
        "$table->unsignedTinyInteger('role')->default(0);",
        "$table->boolean('is_active')->default(true);",
        "$table->timestamps();",
        "$table->index('role');"
    ]),
    ("students", [
        "$table->id();",
        "$table->foreignId('user_id')->constrained('users');",
        "$table->string('full_name', 255);",
        "$table->date('dob');",
        "$table->unsignedTinyInteger('gender');",
        "$table->unsignedTinyInteger('grade_level');",
        "$table->string('parent_name', 255);",
        "$table->string('parent_phone', 20);",
        "$table->text('address');",
        "$table->string('zalo_id', 255)->nullable();",
        "$table->text('note')->nullable();",
        "$table->timestamps();"
    ]),
    ("teachers", [
        "$table->id();",
        "$table->foreignId('user_id')->constrained('users');",
        "$table->string('full_name', 255);",
        "$table->string('phone', 20);",
        "$table->string('email', 255);",
        "$table->text('address');",
        "$table->string('bank_name', 100);",
        "$table->string('bank_account_number', 30);",
        "$table->string('bank_account_holder', 100);",
        "$table->unsignedTinyInteger('status');",
        "$table->date('joined_at');",
        "$table->timestamps();"
    ]),
    ("staff", [
        "$table->id();",
        "$table->foreignId('user_id')->constrained('users');",
        "$table->string('full_name', 255);",
        "$table->string('phone', 20);",
        "$table->unsignedTinyInteger('role_type');",
        "$table->string('bank_name', 100);",
        "$table->string('bank_account_number', 30);",
        "$table->string('bank_account_holder', 100);",
        "$table->unsignedTinyInteger('status');",
        "$table->date('joined_at');",
        "$table->timestamps();"
    ]),
    ("subjects", [
        "$table->id();",
        "$table->string('name', 50);",
        "$table->text('description')->nullable();",
        "$table->boolean('is_active')->default(true);",
        "$table->timestamps();"
    ]),
    ("rooms", [
        "$table->id();",
        "$table->string('name', 50)->unique();",
        "$table->unsignedTinyInteger('capacity')->default(0);",
        "$table->text('note')->nullable();",
        "$table->unsignedTinyInteger('status');",
        "$table->timestamps();"
    ]),
    ("classes", [
        "$table->id();",
        "$table->string('code', 50)->unique();",
        "$table->string('name', 50);",
        "$table->foreignId('subject_id')->constrained('subjects');",
        "$table->foreignId('teacher_id')->constrained('teachers');",
        "$table->unsignedTinyInteger('grade_level');",
        "$table->decimal('base_fee_per_session', 10, 0);",
        "$table->decimal('teacher_salary_per_session', 10, 0);",
        "$table->unsignedTinyInteger('max_students')->default(0);",
        "$table->unsignedTinyInteger('status')->default(0);",
        "$table->date('start_at');",
        "$table->date('end_at')->nullable();",
        "$table->timestamps();",
        "$table->index('grade_level');",
        "$table->index('status');"
    ]),
    ("class_enrollments", [
        "$table->id();",
        "$table->foreignId('class_id')->constrained('classes');",
        "$table->foreignId('student_id')->constrained('students');",
        "$table->decimal('fee_per_session', 10, 0)->nullable();",
        "$table->date('fee_effective_from')->nullable();",
        "$table->date('fee_effective_to')->nullable();",
        "$table->timestamp('enrolled_at');",
        "$table->date('left_at')->nullable();",
        "$table->text('note')->nullable();",
        "$table->timestamps();",
        "$table->index(['class_id', 'student_id']);"
    ]),
    ("monthly_reports", [
        "$table->id();",
        "$table->foreignId('teacher_id')->constrained('teachers');",
        "$table->foreignId('class_id')->constrained('classes');",
        "$table->foreignId('student_id')->constrained('students');",
        "$table->string('month', 7);",
        "$table->unsignedTinyInteger('status')->default(0);",
        "$table->text('content');",
        "$table->timestamp('submitted_at')->nullable();",
        "$table->timestamp('reviewed_at')->nullable();",
        "$table->foreignId('reviewed_by')->nullable()->constrained('users');",
        "$table->text('reject_reason')->nullable();",
        "$table->timestamps();"
    ]),
    ("class_schedule_templates", [
        "$table->id();",
        "$table->foreignId('class_id')->constrained('classes');",
        "$table->unsignedTinyInteger('day_of_week');",
        "$table->time('start_time');",
        "$table->time('end_time');",
        "$table->foreignId('room_id')->constrained('rooms');",
        "$table->foreignId('teacher_id')->constrained('teachers');",
        "$table->date('start_date');",
        "$table->date('end_date')->nullable();",
        "$table->foreignId('created_by')->constrained('users');",
        "$table->timestamps();",
        "$table->index(['class_id', 'end_date']);"
    ]),
    ("schedule_instances", [
        "$table->id();",
        "$table->foreignId('class_id')->constrained('classes');",
        "$table->foreignId('template_id')->nullable()->constrained('class_schedule_templates');",
        "$table->date('date');",
        "$table->time('start_time');",
        "$table->time('end_time');",
        "$table->foreignId('room_id')->constrained('rooms');",
        "$table->foreignId('teacher_id')->constrained('teachers');",
        "$table->foreignId('original_teacher_id')->constrained('teachers');",
        "$table->decimal('teacher_salary_snapshot', 10, 0);",
        "$table->decimal('custom_salary', 10, 0)->nullable();",
        "$table->unsignedTinyInteger('schedule_type');",
        "$table->unsignedTinyInteger('status');",
        "$table->foreignId('linked_makeup_for')->nullable()->constrained('schedule_instances');",
        "$table->unsignedTinyInteger('fee_type');",
        "$table->decimal('custom_fee_per_session', 10, 0)->nullable();",
        "$table->text('note')->nullable();",
        "$table->foreignId('created_by')->constrained('users');",
        "$table->timestamps();",
        "$table->index(['class_id', 'date']);",
        "$table->index(['room_id', 'date', 'start_time', 'end_time'], 'schedule_room_time_idx');",
        "$table->index(['teacher_id', 'date', 'start_time', 'end_time'], 'schedule_teacher_time_idx');",
        "$table->index(['schedule_type', 'date']);"
    ]),
    ("schedule_change_requests", [
        "$table->id();",
        "$table->foreignId('schedule_instance_id')->constrained('schedule_instances');",
        "$table->foreignId('requested_by')->constrained('teachers');",
        "$table->date('proposed_date');",
        "$table->time('proposed_start_time');",
        "$table->time('proposed_end_time');",
        "$table->foreignId('proposed_room_id')->nullable()->constrained('rooms');",
        "$table->foreignId('proposed_teacher_id')->nullable()->constrained('teachers');",
        "$table->text('reason');",
        "$table->unsignedTinyInteger('status')->default(0);",
        "$table->foreignId('reviewed_by')->nullable()->constrained('users');",
        "$table->timestamp('reviewed_at')->nullable();",
        "$table->text('rejected_reason')->nullable();",
        "$table->timestamps();"
    ]),
    ("attendance_sessions", [
        "$table->id();",
        "$table->foreignId('schedule_instance_id')->constrained('schedule_instances');",
        "$table->foreignId('class_id')->constrained('classes');",
        "$table->foreignId('teacher_id')->constrained('teachers');",
        "$table->date('session_date');",
        "$table->text('lesson_content')->nullable();",
        "$table->text('homework')->nullable();",
        "$table->text('next_session_note')->nullable();",
        "$table->text('general_note')->nullable();",
        "$table->unsignedTinyInteger('status');",
        "$table->timestamp('completed_at')->nullable();",
        "$table->timestamp('locked_at')->nullable();",
        "$table->timestamps();",
        "$table->index(['class_id', 'session_date']);",
        "$table->index('status');"
    ]),
    ("attendance_records", [
        "$table->id();",
        "$table->foreignId('session_id')->constrained('attendance_sessions');",
        "$table->foreignId('student_id')->constrained('students');",
        "$table->unsignedTinyInteger('status');",
        "$table->time('check_in_time')->nullable();",
        "$table->boolean('is_fee_counted')->default(false);",
        "$table->text('teacher_comment')->nullable();",
        "$table->text('private_note')->nullable();",
        "$table->timestamps();",
        "$table->unique(['session_id', 'student_id']);"
    ]),
    ("scores", [
        "$table->id();",
        "$table->foreignId('attendance_record_id')->constrained('attendance_records');",
        "$table->unsignedTinyInteger('exam_slot');",
        "$table->string('exam_name', 100)->nullable();",
        "$table->decimal('score', 5, 2)->nullable();",
        "$table->decimal('max_score', 5, 2)->default(10);",
        "$table->text('note')->nullable();",
        "$table->timestamps();",
        "$table->unique(['attendance_record_id', 'exam_slot'], 'score_record_slot_unique');",
        "$table->index('exam_slot');"
    ]),
    ("reward_points", [
        "$table->id();",
        "$table->foreignId('student_id')->constrained('students');",
        "$table->foreignId('session_id')->nullable()->constrained('attendance_sessions');",
        "$table->integer('amount');",
        "$table->string('reason', 255)->nullable();",
        "$table->foreignId('awarded_by')->constrained('users');",
        "$table->timestamps();"
    ]),
    ("reward_items", [
        "$table->id();",
        "$table->string('name', 100);",
        "$table->unsignedInteger('points_required');",
        "$table->unsignedTinyInteger('reward_type');",
        "$table->decimal('discount_amount', 10, 0)->nullable();",
        "$table->boolean('is_active')->default(true);",
        "$table->timestamps();",
        "$table->index('reward_type');",
        "$table->index('is_active');"
    ]),
    ("tuition_invoices", [
        "$table->id();",
        "$table->string('invoice_number', 20);",
        "$table->foreignId('student_id')->constrained('students');",
        "$table->foreignId('class_id')->constrained('classes');",
        "$table->string('month', 7);",
        "$table->integer('total_sessions');",
        "$table->integer('attended_sessions');",
        "$table->decimal('total_study_fee', 10, 0);",
        "$table->decimal('discount_amount', 10, 0)->default(0);",
        "$table->decimal('previous_debt', 10, 0)->default(0);",
        "$table->decimal('total_amount', 10, 0);",
        "$table->decimal('paid_amount', 10, 0)->default(0);",
        "$table->unsignedTinyInteger('status');",
        "$table->boolean('is_locked')->default(false);",
        "$table->text('note')->nullable();",
        "$table->timestamps();",
        "$table->unique(['student_id', 'class_id', 'month']);"
    ]),
    ("reward_redemptions", [
        "$table->id();",
        "$table->foreignId('student_id')->constrained('students');",
        "$table->foreignId('reward_item_id')->constrained('reward_items');",
        "$table->unsignedInteger('points_spent');",
        "$table->timestamp('redeemed_at');",
        "$table->foreignId('processed_by')->constrained('users');",
        "$table->foreignId('invoice_id')->nullable()->constrained('tuition_invoices');",
        "$table->timestamps();"
    ]),
    ("teacher_salary_configs", [
        "$table->id();",
        "$table->foreignId('teacher_id')->constrained('teachers');",
        "$table->foreignId('class_id')->constrained('classes');",
        "$table->decimal('salary_per_session', 10, 0);",
        "$table->date('effective_from');",
        "$table->date('effective_to')->nullable();",
        "$table->timestamps();",
        "$table->index(['teacher_id', 'class_id']);"
    ]),
    ("staff_shifts", [
        "$table->id();",
        "$table->foreignId('staff_id')->constrained('staff');",
        "$table->date('shift_date');",
        "$table->dateTime('check_in_time');",
        "$table->dateTime('check_out_time');",
        "$table->decimal('total_hours', 4, 2);",
        "$table->decimal('hourly_rate_snapshot', 10, 0);",
        "$table->decimal('total_salary', 10, 0);",
        "$table->unsignedTinyInteger('status');",
        "$table->text('note')->nullable();",
        "$table->timestamps();",
        "$table->index(['staff_id', 'shift_date']);"
    ]),
    ("staff_salary_configs", [
        "$table->id();",
        "$table->foreignId('staff_id')->constrained('staff');",
        "$table->unsignedTinyInteger('salary_type');",
        "$table->decimal('salary_amount', 10, 0);",
        "$table->date('effective_from');",
        "$table->date('effective_to')->nullable();",
        "$table->timestamps();"
    ]),
    ("tuition_invoice_logs", [
        "$table->id();",
        "$table->foreignId('invoice_id')->constrained('tuition_invoices');",
        "$table->decimal('amount', 10, 0);",
        "$table->dateTime('paid_at');",
        "$table->text('note')->nullable();",
        "$table->boolean('is_cancelled')->default(false);",
        "$table->dateTime('cancelled_at')->nullable();",
        "$table->text('cancel_reason')->nullable();",
        "$table->foreignId('changed_by')->constrained('users');",
        "$table->timestamps();"
    ]),
    ("teacher_salary_invoices", [
        "$table->id();",
        "$table->foreignId('teacher_id')->constrained('teachers');",
        "$table->foreignId('class_id')->constrained('classes');",
        "$table->string('month', 7);",
        "$table->integer('total_sessions');",
        "$table->decimal('bonus', 10, 0)->default(0);",
        "$table->decimal('penalty', 10, 0)->default(0);",
        "$table->decimal('total_amount', 10, 0);",
        "$table->decimal('paid_amount', 10, 0)->default(0);",
        "$table->unsignedTinyInteger('status');",
        "$table->boolean('is_locked')->default(false);",
        "$table->text('note')->nullable();",
        "$table->timestamps();",
        "$table->unique(['teacher_id', 'class_id', 'month']);"
    ]),
    ("teacher_salary_invoice_logs", [
        "$table->id();",
        "$table->foreignId('invoice_id')->constrained('teacher_salary_invoices');",
        "$table->decimal('amount', 10, 0);",
        "$table->dateTime('paid_at');",
        "$table->text('note')->nullable();",
        "$table->boolean('is_cancelled')->default(false);",
        "$table->dateTime('cancelled_at')->nullable();",
        "$table->text('cancel_reason')->nullable();",
        "$table->foreignId('changed_by')->constrained('users');",
        "$table->timestamps();"
    ]),
    ("staff_salary_invoices", [
        "$table->id();",
        "$table->foreignId('staff_id')->constrained('staff');",
        "$table->string('month', 7);",
        "$table->decimal('base_salary', 10, 0);",
        "$table->decimal('bonus', 10, 0)->default(0);",
        "$table->decimal('penalty', 10, 0)->default(0);",
        "$table->decimal('advance_amount', 10, 0)->default(0);",
        "$table->decimal('total_amount', 10, 0);",
        "$table->decimal('paid_amount', 10, 0)->default(0);",
        "$table->unsignedTinyInteger('status');",
        "$table->boolean('is_locked')->default(false);",
        "$table->text('note')->nullable();",
        "$table->timestamps();",
        "$table->unique(['staff_id', 'month']);"
    ]),
    ("staff_salary_invoice_logs", [
        "$table->id();",
        "$table->foreignId('invoice_id')->constrained('staff_salary_invoices');",
        "$table->decimal('amount', 10, 0);",
        "$table->dateTime('paid_at');",
        "$table->text('note')->nullable();",
        "$table->boolean('is_cancelled')->default(false);",
        "$table->dateTime('cancelled_at')->nullable();",
        "$table->text('cancel_reason')->nullable();",
        "$table->foreignId('changed_by')->constrained('users');",
        "$table->timestamps();"
    ]),
    ("expense_categories", [
        "$table->id();",
        "$table->string('name', 255)->unique();",
        "$table->text('description')->nullable();",
        "$table->timestamps();"
    ]),
    ("expense_invoices", [
        "$table->id();",
        "$table->foreignId('category_id')->constrained('expense_categories');",
        "$table->string('title', 255);",
        "$table->unsignedTinyInteger('status');",
        "$table->string('month', 7);",
        "$table->decimal('amount', 10, 0);",
        "$table->dateTime('paid_at')->nullable();",
        "$table->text('note')->nullable();",
        "$table->foreignId('changed_by')->constrained('users');",
        "$table->unsignedTinyInteger('payment_method');",
        "$table->foreignId('created_by')->constrained('users');",
        "$table->boolean('is_recurring')->default(false);",
        "$table->timestamps();"
    ]),
    ("user_logs", [
        "$table->id();",
        "$table->foreignId('user_id')->constrained('users');",
        "$table->string('action', 255);",
        "$table->text('description')->nullable();",
        "$table->timestamps();"
    ]),
    ("notifications", [
        "$table->id();",
        "$table->foreignId('user_id')->constrained('users');",
        "$table->string('title', 255);",
        "$table->text('content');",
        "$table->unsignedTinyInteger('type');",
        "$table->boolean('is_read')->default(false);",
        "$table->timestamp('read_at')->nullable();",
        "$table->unsignedTinyInteger('channel');",
        "$table->unsignedTinyInteger('send_status');",
        "$table->timestamp('sent_at')->nullable();",
        "$table->boolean('is_urgent')->default(false);",
        "$table->nullableMorphs('reference');",
        "$table->timestamps();"
    ])
]

os.makedirs('database/migrations', exist_ok=True)

# Helper to convert snake_case to CamelCase (pascal case)
def to_camel(name):
    return ''.join(word.capitalize() for word in name.split('_'))

start_time = datetime.datetime.now()

template = """<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{{
    /**
     * Run the migrations.
     */
    public function up(): void
    {{
        Schema::create('{table_name}', function (Blueprint $table) {{
{columns}
        }});
    }}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {{
        Schema::dropIfExists('{table_name}');
    }}
}};
"""

for i, (table_name, columns) in enumerate(tables):
    # Time offset to ensure ordered migrations
    dt = start_time + datetime.timedelta(seconds=i)
    time_str = dt.strftime('%Y_%m_%d_%H%M%S')
    filename = f"database/migrations/{time_str}_create_{table_name}_table.php"
    
    col_str = ""
    for col in columns:
        col_str += f"            {col}\n"
    
    content = template.format(table_name=table_name, columns=col_str.rstrip())
    
    with open(filename, 'w', encoding='utf-8') as f:
        f.write(content)
        
print("Successfully generated all migrations.")
