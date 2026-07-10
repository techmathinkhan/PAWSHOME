<?php
/**
 * PawsHome — AI Pet Recommendation Assistant
 *
 * POST /api/chat
 * Body: { "message": "...", "history": [...] }
 *
 * Strategy (no paid API key needed):
 *   1. Load all available pets from the database
 *   2. Build a rich system prompt with real pet data
 *   3. If ANTHROPIC_KEY is set → use Claude API
 *   4. Otherwise → use built-in rule-based engine that
 *      pattern-matches the user's message and returns
 *      smart recommendations from the live database
 *
 * Set your Anthropic key in config/database.php as:
 *   define('ANTHROPIC_API_KEY', 'sk-ant-...');
 * or leave it empty for the rule-based engine.
 */
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

$d       = body();
$message = trim($d['message'] ?? '');
if (!$message) jsonError('Message is required');

$history = is_array($d['history'] ?? null) ? $d['history'] : [];

/* ── LOAD LIVE PET DATA ─────────────────────────────────────── */
$db   = getDB();
$pets = $db->query(
    "SELECT id,name,species,breed,age,gender,vaccinated,color,weight,
            description,health_notes,status
     FROM pets WHERE status = 'available'
     ORDER BY created_at DESC"
)->fetchAll();

/* ── TRY CLAUDE API (if key configured) ────────────────────── */
$apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '';

if ($apiKey) {
    $petContext = buildPetContext($pets);
    $reply = callClaudeAPI($apiKey, $message, $history, $petContext);
    if ($reply) {
        jsonOK(['reply' => $reply, 'source' => 'claude', 'pets' => []]);
    }
}

/* ── RULE-BASED ENGINE (no API key needed) ──────────────────── */
$result = ruleBasedRecommend($message, $pets);
jsonOK(['reply' => $result['reply'], 'source' => 'local', 'pets' => $result['matches']]);


/* ══════════════════════════════════════════════════════════════
   HELPER FUNCTIONS
══════════════════════════════════════════════════════════════ */

function buildPetContext(array $pets): string {
    if (!$pets) return "No pets currently available.";
    $lines = [];
    foreach ($pets as $p) {
        $vacc  = $p['vaccinated'] ? 'vaccinated' : 'not vaccinated';
        $age   = $p['age'] < 1 ? round($p['age']*12).'mo' : $p['age'].'yr';
        $lines[] = "- {$p['name']} ({$p['species']}, {$p['breed']}, {$age}, {$p['gender']}, $vacc, ID:{$p['id']}): {$p['description']}";
    }
    return implode("\n", $lines);
}

function callClaudeAPI(string $key, string $msg, array $history, string $context): ?string {
    $systemPrompt = "You are PawsBot, the friendly AI adoption assistant for PawsHome shelter in Bangalore, India.
Your job is to help people find their perfect pet companion.

Currently available pets at our shelter:
$context

Guidelines:
- Suggest specific pets by name from the list above when relevant
- Keep replies warm, concise (3-5 sentences), and encouraging
- Mention pet ID numbers so users can look them up easily
- Give brief care tips relevant to recommended pets
- If no pet matches perfectly, suggest what to look for and invite them to check back
- Never make up pets that aren't in the list";

    // Build messages array with history
    $messages = [];
    foreach ($history as $h) {
        if (!empty($h['role']) && !empty($h['content'])) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $msg];

    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 400,
        'system'     => $systemPrompt,
        'messages'   => $messages,
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$res) return null;
    $data = json_decode($res, true);
    return $data['content'][0]['text'] ?? null;
}

/**
 * Rule-based recommendation engine.
 * Pattern-matches the user's message against keywords and
 * scores/ranks available pets accordingly.
 */
function ruleBasedRecommend(string $msg, array $pets): array {
    $msg  = strtolower($msg);
    $reply = '';
    $matches = [];

    /* ── INTENT DETECTION ─── */
    $isAboutApartment   = str_contains($msg, 'apartment') || str_contains($msg, 'flat') || str_contains($msg, 'small space') || str_contains($msg, 'small home');
    $isAboutKids        = str_contains($msg, 'kid') || str_contains($msg, 'child') || str_contains($msg, 'baby') || str_contains($msg, 'toddler') || str_contains($msg, 'family');
    $isAboutAllergy     = str_contains($msg, 'allerg') || str_contains($msg, 'shed') || str_contains($msg, 'fur');
    $isAboutLowMaint    = str_contains($msg, 'low maintenance') || str_contains($msg, 'busy') || str_contains($msg, 'work a lot') || str_contains($msg, 'not home') || str_contains($msg, 'office');
    $isAboutDogs        = str_contains($msg, 'dog') || str_contains($msg, 'puppy') || str_contains($msg, 'pup');
    $isAboutCats        = str_contains($msg, 'cat') || str_contains($msg, 'kitten') || str_contains($msg, 'feline');
    $isAboutSmall       = str_contains($msg, 'small pet') || str_contains($msg, 'hamster') || str_contains($msg, 'rabbit') || str_contains($msg, 'bird') || str_contains($msg, 'tiny');
    $isAboutActive      = str_contains($msg, 'active') || str_contains($msg, 'exercise') || str_contains($msg, 'run') || str_contains($msg, 'outdoor') || str_contains($msg, 'walk');
    $isAboutFirstPet    = str_contains($msg, 'first pet') || str_contains($msg, 'first time') || str_contains($msg, 'beginner') || str_contains($msg, 'never had');
    $isAboutSenior      = str_contains($msg, 'senior') || str_contains($msg, 'old pet') || str_contains($msg, 'older');
    $isAvailability     = str_contains($msg, 'available') || str_contains($msg, 'what pets') || str_contains($msg, 'show me') || str_contains($msg, 'list');
    $isGreeting         = preg_match('/^(hi|hello|hey|namaste|good morning|good afternoon|good evening|howdy)/i', trim($msg));
    $isAdoptProcess     = str_contains($msg, 'adopt') || str_contains($msg, 'how do i') || str_contains($msg, 'process') || str_contains($msg, 'application');
    $isVaccine          = str_contains($msg, 'vacc') || str_contains($msg, 'health') || str_contains($msg, 'vet');

    /* ── SCORE PETS ─── */
    $scored = [];
    foreach ($pets as $pet) {
        $score = 0;
        $s     = strtolower($pet['species']);
        $d     = strtolower($pet['description'] . ' ' . $pet['health_notes']);
        $age   = (float)$pet['age'];

        // Species preference
        if ($isAboutDogs  && $s === 'dog')     $score += 10;
        if ($isAboutCats  && $s === 'cat')     $score += 10;
        if ($isAboutSmall && in_array($s, ['hamster','rabbit','bird'])) $score += 10;

        // Apartment suitability
        if ($isAboutApartment) {
            if (in_array($s, ['cat','hamster','rabbit','bird'])) $score += 8;
            if ($s === 'dog' && (str_contains($d,'calm') || str_contains($d,'quiet') || str_contains($d,'indoor'))) $score += 6;
            if ($s === 'dog' && $pet['breed'] === 'Beagle') $score -= 2; // beagles vocal
        }

        // Kid-friendly
        if ($isAboutKids) {
            if (str_contains($d,'child') || str_contains($d,'kid') || str_contains($d,'famil')) $score += 8;
            if ($s === 'dog') $score += 4;
            if ($s === 'hamster' && $age < 1) $score += 5; // good starter for kids
        }

        // Allergy / shedding
        if ($isAboutAllergy) {
            if (in_array($s, ['fish','bird','reptile'])) $score += 8;
            if ($s === 'hamster' || $s === 'rabbit') $score += 4;
        }

        // Low maintenance
        if ($isAboutLowMaint) {
            if (in_array($s, ['cat','hamster','bird'])) $score += 8;
            if ($s === 'cat' && (str_contains($d,'independent') || str_contains($d,'quiet'))) $score += 4;
        }

        // Active lifestyle
        if ($isAboutActive) {
            if ($s === 'dog' && $age <= 3) $score += 8;
            if (str_contains($d,'energetic') || str_contains($d,'active') || str_contains($d,'run')) $score += 4;
        }

        // First pet
        if ($isAboutFirstPet) {
            if (in_array($s, ['cat','hamster'])) $score += 6;
            if ($s === 'dog' && (str_contains($d,'gentle') || str_contains($d,'easy') || str_contains($d,'train'))) $score += 4;
        }

        // Senior pet preference
        if ($isAboutSenior && $age >= 5) $score += 7;

        // Vaccinated bonus for health-conscious
        if ($isVaccine && $pet['vaccinated']) $score += 3;

        $scored[] = ['pet' => $pet, 'score' => $score];
    }

    // Sort by score desc
    usort($scored, fn($a,$b) => $b['score'] - $a['score']);
    $topPets = array_slice($scored, 0, 3);
    $matches = array_map(fn($x) => $x['pet'], $topPets);

    /* ── BUILD REPLY ─── */
    if ($isGreeting) {
        $count = count($pets);
        $reply = "Hello! 🐾 Welcome to PawsHome! I'm PawsBot, your personal pet adoption assistant. "
               . "We currently have **$count pets** looking for their forever homes in Bangalore. "
               . "Tell me about your lifestyle — like your home size, whether you have kids, or how active you are — "
               . "and I'll suggest the perfect companion for you! What would you like to know?";
        $matches = array_slice($pets, 0, 3);

    } elseif ($isAdoptProcess) {
        $reply = "🏠 **How to Adopt from PawsHome:**\n\n"
               . "1. **Browse pets** — Use our filters to find your match\n"
               . "2. **Submit an Application** — Click 'Apply to Adopt' on any pet's page\n"
               . "3. **Under Review** — Our team reviews your application within 48 hours\n"
               . "4. **Interview Scheduled** — We'll arrange a virtual or in-person chat\n"
               . "5. **Approved** — Complete paperwork and pay the nominal adoption fee\n"
               . "6. **Welcome Home!** — Your new companion joins the family 🎉\n\n"
               . "Ready to start? Which pet caught your eye?";

    } elseif ($isAvailability && !$isAboutDogs && !$isAboutCats && !$isAboutSmall) {
        $names = implode(', ', array_map(fn($p) => $p['name'].' ('.$p['species'].')', $pets));
        $reply = "🐾 Currently available at PawsHome: **$names**. "
               . "Tell me about your home and lifestyle and I'll narrow down the perfect match for you!";

    } elseif ($isAboutApartment && $isAboutKids) {
        $reply = "Great combination! For an **apartment with kids**, I'd suggest:\n\n"
               . "🐱 **Cats** — Independent, quiet, great with gentle children. Luna (Persian, ID:2) is calm and affectionate.\n"
               . "🐰 **Rabbits** — Quiet, soft, teach kids responsibility. Coco (Holland Lop, ID:4) is perfect for families.\n"
               . "🐹 **Hamsters** — Ideal starter pets for children to learn animal care. Hazel (Syrian Hamster, ID:8) is friendly and easy to keep.\n\n"
               . "Dogs can work too — Bella (Beagle, ID:6) is gentle with kids but needs daily outdoor time. Want more details on any of these?";

    } elseif ($isAboutApartment) {
        $reply = "For **apartment living**, the best choices are usually:\n\n"
               . "🐱 **Cats** — Self-sufficient, quiet, perfect for smaller spaces. Luna (Persian, ID:2) is our most apartment-friendly cat.\n"
               . "🐰 **Rabbits** — Clean and quiet. Coco (Holland Lop, ID:4) lives happily in a moderate enclosure.\n"
               . "🐦 **Birds** — Tweety (Budgerigar, ID:5) is cheerful and needs minimal space.\n"
               . "🐹 **Hamsters** — Hazel (ID:8) is the ultimate low-footprint pet.\n\n"
               . "If you'd like a dog, Bella (Beagle, ID:6) adapts reasonably well with regular walks. Any questions?";

    } elseif ($isAboutKids) {
        $reply = "🎉 Pets are wonderful for families with children! The most **child-friendly options** available:\n\n"
               . "🐶 **Bella** (Beagle, ID:6) — Gentle, patient, loves kids. Excellent with families.\n"
               . "🐶 **Buddy** (Golden Retriever, ID:1) — The classic family dog, fully trained and vaccinated.\n"
               . "🐰 **Coco** (Holland Lop, ID:4) — Soft and gentle, perfect for teaching kids responsibility.\n\n"
               . "Tip: Always supervise young children with any pet and teach them gentle handling. Want to apply for any of these?";

    } elseif ($isAboutLowMaint || str_contains($msg, 'busy')) {
        $reply = "For **busy lifestyles**, these pets thrive with independent care:\n\n"
               . "🐱 **Luna** (Persian Cat, ID:2) — Cats are the perfect companion for working professionals. Self-grooming, quiet, happy alone.\n"
               . "🐦 **Tweety** (Budgerigar, ID:5) — 15 minutes of interaction daily is enough.\n"
               . "🐹 **Hazel** (Hamster, ID:8) — Fully nocturnal; active while you sleep!\n\n"
               . "Avoid dogs if you're away 8+ hours daily — they need regular outdoor time and company.";

    } elseif ($isAboutActive) {
        $reply = "🏃 For an **active person**, dogs are the perfect match!\n\n"
               . "🐶 **Buddy** (Golden Retriever, ID:1) — Loves long walks, fetch, and outdoor adventures. He's 2 years old and fully energised.\n"
               . "🐶 **Bella** (Beagle, ID:6) — Born explorer with a nose for adventure. Great running companion.\n\n"
               . "Both are vaccinated and house-trained. Buddy especially would thrive with someone who loves morning runs. Interested?";

    } elseif ($isAboutDogs) {
        $dogs = array_filter($pets, fn($p) => strtolower($p['species']) === 'dog');
        $desc = implode('; ', array_map(fn($p) => "{$p['name']} ({$p['breed']}, {$p['age']}yr, ID:{$p['id']})", $dogs));
        $reply = "🐶 Our available dogs: **$desc**.\n\n"
               . "Buddy is our most family-friendly retriever, Bella is great with children, and Max was recently adopted — a lovely success story! "
               . "Which one interests you? Tell me more about your home and I'll help you decide.";
        $matches = array_values($dogs);

    } elseif ($isAboutCats) {
        $cats = array_filter($pets, fn($p) => strtolower($p['species']) === 'cat');
        $desc = implode('; ', array_map(fn($p) => "{$p['name']} ({$p['breed']}, {$p['age']}yr, ID:{$p['id']})", $cats));
        $reply = "🐱 Our available cats: **$desc**.\n\n"
               . "Luna is a calm, elegant Persian — ideal for apartment living. "
               . "Would you like to know about their personalities, health status, or how to apply?";
        $matches = array_values($cats);

    } elseif ($isAboutFirstPet) {
        $reply = "Welcome to pet parenthood! 🎉 For **first-time owners**, I recommend:\n\n"
               . "🐹 **Hazel** (Hamster, ID:8) — Low maintenance, great for learning animal care basics.\n"
               . "🐱 **Luna** (Persian Cat, ID:2) — Independent and gentle, perfect for beginners.\n"
               . "🐰 **Coco** (Rabbit, ID:4) — Quiet, clean, and affectionate once comfortable.\n\n"
               . "If you're ready for more responsibility, **Bella** (Beagle, ID:6) is gentle and forgiving with new owners. What's your living situation like?";

    } else {
        // Generic helpful response with top matches
        $topNames = implode(', ', array_slice(array_map(fn($p) => $p['name'], $pets), 0, 5));
        $reply = "I'd love to help you find your perfect companion! 🐾 "
               . "We currently have **$topNames** and more available. "
               . "To make the best recommendation, could you tell me:\n\n"
               . "• 🏠 Your living space (apartment/house/garden?)\n"
               . "• 👨‍👩‍👧 Anyone at home (kids, elderly, other pets?)\n"
               . "• ⏰ How many hours you're typically home each day\n"
               . "• 🏃 Your activity level (active/moderate/relaxed?)\n\n"
               . "The more I know, the better I can match you!";
    }

    // Append matched pets if we have specific ones
    if (!empty($topPets) && $topPets[0]['score'] > 0 && !$isGreeting && !$isAdoptProcess) {
        $matches = array_map(fn($x) => $x['pet'], array_filter($topPets, fn($x) => $x['score'] > 3));
    }

    return ['reply' => $reply, 'matches' => array_values($matches)];
}
