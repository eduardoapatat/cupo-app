# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

All four roles live in the **membership** (person × studio), never on the user. One person can hold different roles in different studios, and gets a studio switcher only when they belong to more than one. There is no platform super admin and no public marketplace between studios.

- **Owner (`owner`, «Dueña»)**: runs a small group-class studio for adults (pilates, yoga, functional training, dance) in Chile, often a single room for about 10 people. She signs up, creates her studio and becomes its only owner. She makes the business decisions: plans and prices, weekly schedule and class types, rules, staff and reports. She can also do everything the front desk does and teach sessions. On arrival she sees today's occupancy, alerts (pending receipts, registers with a cash difference, today's voids) and the month's income.
- **Front desk (`front_desk`, «Recepción»)**: the person at the counter, invited by the owner by email. Enrolls students, sells plans at list price, takes payments, validates transfer receipts, opens and closes their own cash register and books or cancels on behalf of students within the rules. Follows the rules but never changes them. On arrival sees today's sessions, the students arriving, receipts to validate and the state of their register.
- **Instructor (`instructor`)**: teaches sessions and is paid per session taught; never handles money. Invited by the owner by email. Sees only their own sessions with who is coming (names only) and marks attendance. On arrival sees today's sessions.
- **Student (`student`, «Alumna»)**: an adult who takes classes and pays for a plan. Either joins through the studio's enrollment link and books from her phone, or is enrolled at the counter with just a name and phone and never uses the app (she can activate it later with her email). Manages only herself. On arrival sees her next session, how many classes she has left and when her plan expires.

The front desk works on a desktop at the counter and on a phone; students and instructors use mostly phones. Every screen must work well at both sizes.

## Product Purpose

Small studios sell plans («4 u 8 clases al mes», «ilimitado») and run everything over WhatsApp, a notebook and a spreadsheet. Sessions get overbooked, or spots stay empty after last-minute cancellations; nobody knows for sure how many classes each student has left; bank transfers are checked by hand; and plans expire without notice.

Cupo gives each studio one app. Success means: a student books in two taps knowing how many classes she has left; a session is **never** overbooked; the owner knows at once who paid, who owes and how sessions fill; and the front desk takes payments and balances the register without spreadsheets.

## Positioning

Spots are guaranteed by the database: confirmed bookings never exceed a session's capacity, even under truly concurrent requests. Credits, payments and cash registers keep a full trail (who, when and why), and a closed register can only be changed by the owner, so whoever receives the money cannot erase it afterwards.

## Operating Context

- **Schedule:** each studio defines class types (name, duration, capacity) and a weekly schedule with an instructor; concrete sessions are generated from it by the scheduler.
- **Credits:** booking takes 1 credit from a valid plan. Cancelling at least 6 hours before the session returns it; later cancellations and no-shows lose it. Bookings close 1 hour before the session. Every credit movement is recorded.
- **Waitlist:** when a session is full the student can tap «Inscribirme si se libera un lugar». A freed spot is booked automatically for the first person in line (taking 1 credit) only while the session is at least 6 hours away, the same as the cancellation window by default. Closer to the start the spot stays open for anyone, so nobody gets a booking she can no longer cancel for free.
- **Plans:** they expire N days after purchase, and students are told 3 days before. Each plan sets its own daily limit; unlimited plans start at 1 session a day.
- **Payments:** at the counter first (cash, card through an external POS, or transfer), recorded in the register of whoever takes the payment, plus transfers with a receipt that the front desk or the owner validates. Webpay Plus (sandbox) arrives at the end of phase 3.
- **Cash register:** one per person per day. Closing it shows the totals per method and the difference between declared and expected cash. After closing, only the owner can void its payments.
- **Rules:** the defaults above live in `config/studio.php`. Each studio gets its own copy when it is created, and the owner can change them.
- **Time and money:** times are stored in UTC and shown in `America/Santiago`; money is Chilean pesos (CLP) as integers, never decimals.

## Capabilities and Constraints

- The whole UI is in Spanish (neutral «tú», no Chilean slang); code is in English. There is no i18n library: Spanish text is written directly in the React components.
- Fortify auth URLs stay in English (`/login`, `/settings/...`); product URLs are Spanish (`/inicio`, `/estudios/{slug}/...`).
- Stack: Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4 and shadcn/ui (new-york style, Radix, lucide icons) from the official starter kit.
- **One single authenticated app** inside the starter's sidebar layout, with a menu that changes by role. There is no public site to design. It uses only the starter's shadcn/ui components, as they are; missing ones are added with the shadcn CLI, unmodified.
- Every menu item of every role exists from phase 0; the ones not built yet are shown dimmed with «Pronto». Menus, arrival screens and the permission matrix are defined in `docs/roles-y-permisos.md`.
- Permissions are granted per action (Gates and Policies), never with `if role === ...`. A resource from another studio answers 404, not 403.
- **Data minimization:** no national ID (RUT), birth date or health data; no minors (people declare they are 18 or older when joining); email is optional for students enrolled at the counter.
- Out of scope, on purpose: HR, accounting, multiple branches, health data, minors, a public marketplace or catalog of studios, and a platform super admin.
- Undecided: deployment platform (decided in phase 7).

## Brand Commitments

- Name: **Cupo** (a «cupo» is a spot in a class).
- Voice: close and neutral Spanish, using «tú». Labels are short and literal («Agenda», «Alumnas», «Vender plan», «Caja», «Mi plan»).
- No real brands, studios, people or personal data anywhere.

## Evidence on Hand

- Fictional demo data only: the studio «Estudio Flow» in Ñuñoa and one demo account per role (Carla, the owner; Camila at the front desk; Fernanda and Diego, instructors; Javiera, a student), plus Rosa, a student without the app, and «Estudio Sur» (Paula, the owner), where Fernanda also teaches. All seeded by `database/seeders`.
- Market evidence is general to Chilean small businesses (technology adoption, transfers handled by hand, late collections), cited in the README. Evidence specific to studios is weak and commercial, and must be presented as such.
- No logo, brand assets, photography, testimonials, customers or metrics exist. Do not fabricate any of them.

## Portfolio Context

Cupo is a fictional product built as a portfolio project to show Laravel/React skills to technical recruiters in Chile and Latin America. Reviewers judge code quality, correctness and how real the product feels, so the UI should look like a credible working product, not a template. Everything shown must be truthful to the fictional studio.

## Product Principles

1. Never sell a spot that does not exist: capacity is enforced by the database, not by hope.
2. A student always knows how many classes she has left and when her plan expires.
3. Money leaves a trail: every payment, void and register close records who, when and why.
4. Each role sees only what it needs, and a student only herself.
5. Prefer plain, literal Spanish over clever copy; every error says what happened and what to do next.
6. Money and time are always exact: CLP integers and Santiago local time, never rounded or ambiguous.

## Accessibility & Inclusion

Target WCAG 2.2 AA: sufficient contrast, full keyboard navigation, visible focus, labelled form fields and errors announced to assistive technology. Students book mostly from phones, so touch targets and one-handed use matter.
