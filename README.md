# 🐾 PawsHome v4 — Full Adoption Platform (PHP + MySQL + AI)

## 🆕 What's New in v4

1. **Adoption Application Workflow** — Full pipeline: Submitted → Under Review → Interview Scheduled → Approved/Rejected → Adopted (auto-updates pet status)
2. **Lost & Found Pets Module** — Report lost/found pets with photos, area, contact details
3. **Adoption Success Stories** — Users submit stories with photos; admin approves/features them
4. **AI Pet Recommendation Assistant** — Smart chatbot that recommends real pets from your live database based on lifestyle questions (apartment, kids, busy schedule, etc.) — **works with zero API key**

---

## 🚀 Setup (4 Steps)

### Step 1 — Start XAMPP
Start **Apache** and **MySQL** in XAMPP Control Panel

### Step 2 — Import Database
1. Open `http://localhost/phpmyadmin`
2. **Import** tab → Choose `pawshome_database.sql` → **Go**
3. You'll see 10 tables created with seed data

### Step 3 — Copy to htdocs
```
C:\xampp\htdocs\pawshome\        (Windows)
/Applications/XAMPP/htdocs/pawshome/  (Mac)
```

### Step 4 — Open
`http://localhost/pawshome`

**Demo logins:**
- Admin: `admin@pawshome.com` / `admin123`
- User: `user@example.com` / `user123`

---

## 🤖 About the AI Chatbot — No API Key Needed!

The chatbot ("PawsBot") works in **two modes**:

### Mode 1 — Built-in Rule-Based Engine (Default, Zero Setup)
This is what runs out of the box. It's a **smart local recommendation system** that:
- Reads your live `pets` table in real time
- Pattern-matches user messages against 12+ lifestyle signals (apartment, kids, allergies, busy schedule, active lifestyle, first-time owner, senior pets, etc.)
- Scores every available pet against the detected intent
- Returns natural language responses **with real pet names, IDs, and clickable chips** linking straight to that pet's detail page

Try it — ask things like:
> *"I have a small apartment and two kids. Which pet is suitable?"*
> *"I'm very busy and travel a lot"*
> *"What dogs do you have available?"*

No internet connection to a third-party AI service is required for this mode — it's pure PHP logic reading from MySQL.

### Mode 2 — Real Claude AI (Optional Upgrade)
If you get an Anthropic API key later (https://console.anthropic.com), just paste it into:
```php
// config/database.php
define('ANTHROPIC_API_KEY', 'sk-ant-api03-...');
```
The same chatbot widget will automatically start using full conversational AI instead — no frontend changes needed. The code in `api/chat.php` already has this integration built in (`callClaudeAPI()` function), it just needs the key.

### Mode 3 — Tidio / Crisp (Alternative, Zero Backend Code)
If you'd rather use a managed third-party chat widget instead of/alongside PawsBot:
1. Sign up free at https://www.tidio.com or https://crisp.chat
2. They give you a single `<script>` tag
3. Paste it just before `</body>` in `index.html`
4. Both have free AI auto-reply features that work out of the box — no server code needed at all

**Recommendation:** Keep the built-in PawsBot (Mode 1) since it's already wired to your real pet database — Tidio/Crisp don't know what pets you have unless you manually configure their FAQ/AI training, which is more setup than what you already have.

---

## 📊 New Database Tables

| Table | Purpose |
|---|---|
| `applications` | Adoption application workflow with status pipeline |
| `lost_found` | Lost and found pet reports |
| `success_stories` | User-submitted adoption testimonials |

## 🔌 New API Endpoints

| Method | URL | Auth | Description |
|---|---|---|---|
| POST | /api/applications | User | Submit adoption application |
| GET | /api/applications | Admin | List all applications |
| GET | /api/applications/mine | User | My applications |
| PUT | /api/applications/:id | Admin | Update status (auto-marks pet adopted on approval) |
| POST | /api/lostfound | User | Report lost/found pet (multipart) |
| GET | /api/lostfound | — | List all reports (filterable by type/status) |
| PUT | /api/lostfound/:id | Owner/Admin | Update/resolve report |
| POST | /api/stories | User | Submit success story (multipart) |
| GET | /api/stories | — | List approved stories |
| GET | /api/stories?all=1 | Admin | List all incl. pending |
| PUT | /api/stories/:id | Admin | Approve/feature story |
| POST | /api/chat | — | AI pet recommendation chat |

---

## 🐾 Adoption Workflow Explained

```
Pet status: available
        ↓
User submits Application  →  status: submitted
        ↓
Admin reviews              →  status: under_review
        ↓
Admin schedules interview  →  status: interview_scheduled
        ↓
   ┌────┴────┐
Approved  Rejected
   ↓
Pet auto-updates to "adopted"
adopter_name + adopted_date set automatically
```

Users can track their application's progress visually with the pipeline UI on the Applications page. Admins manage everything from **Admin → Applications**, including a pipeline summary dashboard.

---

## 📁 Full File List

```
pawshome/
├── index.html                  ← Frontend (all pages, single file)
├── .htaccess                   ← URL routing (10 routes added)
├── pawshome_database.sql       ← Import in phpMyAdmin (10 tables)
├── config/
│   └── database.php            ← MySQL + optional Anthropic key
├── api/
│   ├── helpers.php             ← Shared auth/upload/response helpers
│   ├── auth.php
│   ├── pets.php
│   ├── enquiries.php
│   ├── appointments.php
│   ├── blog.php
│   ├── surrender.php
│   ├── stats.php               ← Updated with v4 metrics
│   ├── applications.php        ← NEW: adoption workflow
│   ├── lostfound.php           ← NEW: lost & found
│   ├── stories.php             ← NEW: success stories
│   └── chat.php                ← NEW: AI recommendation engine
└── uploads/                    ← Pet/story/report images
```
