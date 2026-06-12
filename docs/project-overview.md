# School Management System

## Overview

School Management System is a multi-branch school administration platform. A school operates multiple branches (e.g., Madani PathShala, Jabed Ali), and the system manages the full school lifecycle: public student admission, daily attendance, exams and weighted result generation, class promotion, fee collection with online payment, finance tracking, and administrative documents like ID cards and transfer certificates.

The project is built in two phases. Phase 1 is a Laravel REST API backend serving all functionality under `/api/v1`. Phase 2 is a separate frontend that consumes this API. This document covers Phase 1.

## Goals

1. Let a public visitor apply for admission and let admins approve applications into student accounts.
2. Provide role-based access for six roles: super admin, admin, accountant, teacher, student, parent.
3. Scope all data to branches, with super admin overseeing every branch.
4. Let permitted users record daily student attendance and IP-restricted teacher check-ins.
5. Let permitted users enter subject-wise marks and generate weighted annual results.
6. Promote passed students to the next class, in bulk or individually.
7. Collect monthly student fees via SSLCommerz or local payment and issue downloadable receipts.
8. Track income, expenses, and assets per branch.
9. Generate printable PDFs: result sheets, ID cards, money receipts, transfer certificates.
10. Produce filterable reports across finance, students, teachers, and assets.

## Core User Flow

1. A visitor submits the public admission form for a branch and class.
2. An admin reviews the pending application and approves it.
3. The system creates the student account, assigns roll/ID, sends credentials, and enrolls the student in the class for the current session.
4. A teacher signs in, checks in (validated against the branch IP whitelist), and takes daily attendance for their class.
5. During each exam period (first semester, second semester, final), teachers enter subject-wise marks.
6. The system computes grades and GPA per exam, then the annual result as 25% first semester GPA + 25% second semester GPA + 50% final exam GPA.
7. A permitted user searches any student's result and prints it as PDF; students and parents view their own.
8. After final results, an admin clicks Promote to move all passed students to the next class, or promotes students individually.
9. A student or parent pays the monthly fee online via SSLCommerz, or staff records a local payment; a money receipt PDF is generated and the payment posts to income.
10. Admins and accountants review reports filtered by week, month, year, or date range.

## Features

### Authentication, Roles and Permissions
- Token-based authentication with Laravel Sanctum.
- Granular permissions bundled into six roles using spatie/laravel-permission.
- Super admin manages roles, permissions, and user-role assignment.
- "Any permitted user" requirements resolve through permission checks, not role checks.

### Multi-Branch Architecture
- Every branch-scoped record (students, teachers, classes, attendance, finance, assets) carries a branch_id.
- Non-super-admin users belong to exactly one branch; data isolation enforced by global query scopes.
- Super admin can switch branch context and view consolidated data.

### Academic Structure
- Branches, academic sessions, classes, sections, and subjects per class.
- Class-teacher and subject-teacher assignments.

### Student Admission
- Public, unauthenticated admission form with personal info, guardian info, photo, and documents.
- Applications land in a pending state for admin review.
- Approval creates the student user, sends credentials, and enrolls the student; rejection records a reason.

### Teacher Management
- Admin creates teacher profiles with subjects, assigned classes, and branch.
- System generates and sends login credentials.
- Active/inactive status control.

### Student Attendance
- Daily attendance per class/section: present, absent, late, leave.
- One record per student per day enforced by a unique constraint.
- Students and parents view monthly attendance sheets for themselves or their children.

### Teacher Attendance
- Teacher self check-in and check-out.
- Check-in allowed only from IPs whitelisted in branch settings.
- Admin can view and correct teacher attendance.

### Exams and Mark Entry
- Three exams per class per academic year: first semester, second semester, final.
- Permitted users enter marks per student per subject per exam.
- Marks map to grades and grade points via a configurable grading scale (Bangladesh-standard scale seeded by default).

### Result Generation
- Per-exam result: subject marks, grades, and GPA; an F in any subject fails that exam.
- Annual result formula: 25% first semester GPA + 25% second semester GPA + 50% final exam GPA.
- Any permitted user can search any student's result; result sheets download as PDF.

### Student Promotion
- One-click bulk promotion of all passed students in a class to the next class and session.
- Individual student promotion or hold.
- Failed students repeat the class; promotion history is recorded.

### Student Monthly Payment
- Monthly fee invoices auto-generated on the 1st for all active students, with amounts configured per class per branch.
- Online payment via SSLCommerz; local payment recorded by permitted staff.
- Full-month payments by default; partial payments behind a settings toggle.
- Successful payments generate a downloadable money receipt PDF and post automatically to income.

### Income Management
- Entry and listing of income items with category, amount, date, and description.
- Student fee payments appear as system-generated income entries.

### Expense Management
- Entry and listing of expense items with name, price, date, description, and category.

### Asset Management
- Entry and listing of assets with name, description, value, and purchase date.
- Total asset value at a glance, per branch and consolidated.

### Student ID Card Generation
- Permitted users generate ID cards for a student or a whole class.
- Cards include photo, name, ID, class, branch, session, and validity; downloadable as PDF.

### TC System
- Issuing a transfer certificate sets the student status to TC, retaining the record.
- TC students are excluded from attendance, fee generation, and promotion.
- TC document generated as PDF.

### Reports
- Reports on income, expenses, total students, assets, and teachers, plus a profit/loss summary.
- Filters: weekly, monthly, yearly, and custom date range; per branch or consolidated.
- Exportable as PDF.

### Settings
- Global: school identity, academic session, grading scale, SSLCommerz and notification credentials.
- Per-branch: branch info, teacher check-in IP whitelist, class fee amounts.
- Feature toggles such as partial payments.

## Scope

### In Scope
- Laravel REST API under /api/v1 with Sanctum authentication
- Roles and permissions for six roles across multiple branches
- Public admission form and approval pipeline
- Student, teacher, and parent account management (parents linked to one or more students, created by admin)
- Student attendance and IP-restricted teacher attendance
- Exams, mark entry, grading scale, and weighted result generation
- Bulk and individual student promotion
- Monthly fee invoicing, SSLCommerz and local payments, money receipts
- Income, expense, and asset management
- PDF generation: result sheets, ID cards, receipts, transfer certificates, reports
- Filterable reports and application settings
- Database seeders for roles, permissions, grading scale, and demo data

### Out of Scope
- Frontend application (Phase 2)
- Parent self-registration
- Library, hostel, and transport modules
- Online classes and LMS features
- Mobile applications and push notifications
- Payroll for teachers and staff

## Success Criteria

1. A visitor can submit an admission form, and an admin can approve it into a working student account with credentials delivered.
2. Each role sees only the data its permissions and branch allow; super admin sees all branches.
3. A teacher can check in only from a whitelisted IP and can record daily attendance once per student per day.
4. Marks entered across three exams produce a correct annual result using the 25/25/50 weighting, downloadable as PDF.
5. Clicking Promote moves exactly the passed students of a class to the next class; failed students remain.
6. A fee paid through SSLCommerz or the counter produces a money receipt PDF and an automatic income entry.
7. Reports return correct figures for any weekly, monthly, yearly, or custom date-range filter, per branch or consolidated.
8. A TC-issued student is excluded from attendance, invoicing, and promotion while their record remains intact.
