<?php
/**
 * Verbose AI - Multi-Book Grade 8 Student Textbook Evaluation Suite
 * Tests Book 34 (Amharic), Book 35 (Mathematics), and Book 36 (Social Studies)
 * with 10 questions each (definitions, multi-part, summaries, and negative tests).
 */

require_once __DIR__ . '/../src/MCPService.php';
require_once __DIR__ . '/../src/Database.php';

$mcp = new MCPService();
$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "FATAL: Database connection failed.\n");
    exit(1);
}

$suites = [
    34 => [
        'name' => 'Amharic (Mother Tongue) Grade 8 (Book 34)',
        'tests' => [
            ['What is the title of Chapter 1 (ምዕራፍ 1) on page 1 of Amharic Grade 8?', ['ሥራ', '1'], 'Chapter 1 title p.1'],
            ['In Amharic Grade 8, what does ሀሳብ ማጋራት (Think-aloud) mean?', ['ማካፈል', '248'], 'Glossary Think-aloud p.248'],
            ['What is Decoding (መለየት/ማንበብ) according to the Amharic glossary?', ['ድምፅ', '248'], 'Glossary Decoding p.248'],
            ['What does Self-checking strategy (ራስን የመከታተያ ስልት) mean in the Amharic textbook?', ['መረዳታቸውን', '249'], 'Glossary Self-checking p.249'],
            ['What is WPM (ቃላት በደቂቃ) and WCPM in the Amharic textbook?', ['ደቂቃ', '249'], 'Glossary WPM/WCPM p.249'],
            ['What is Rhyme (ቤት መምታት) according to the Amharic glossary?', ['ፊደሎች', '250'], 'Glossary Rhyme p.250'],
            ['What is Choral reading (በጋራ ማንበብ) in the Amharic textbook?', ['ተማሪዎች', '250'], 'Glossary Choral reading p.250'],
            ['What is Narrative text (ተራኪ ጽሑፍ) in the Amharic textbook?', ['ታሪክ', '250'], 'Glossary Narrative text p.250'],
            ['On pages 24 and 44, what are Chapter 2 (ምዕራፍ 2) and Chapter 3 (ምዕራፍ 3) about?', ['ድንቅነሽ', 'አረጋውያን'], 'Multi-part Chapters 2 & 3'],
            ['What is the boiling point of liquid nitrogen in Kelvin?', ['not found in the textbook', 'could not find'], 'Negative test (not in Amharic book)'],
        ]
    ],
    35 => [
        'name' => 'Mathematics Grade 8 (Book 35)',
        'tests' => [
            ['What is a cube number according to Definition 1.5?', ['multiplying', '33'], 'Definition 1.5 cube number'],
            ['What is the cube root of a given number according to Definition 1.6?', ['cube', '38'], 'Definition 1.6 cube root'],
            ['According to the Summary for Unit 1, what is squaring a number?', ['itself', '43'], 'Unit 1 Summary squaring p.43'],
            ['What is an algebraic expression according to Definition 2.5?', ['algebraic'], 'Definition 2.5 algebraic expression'],
            ['How is the product of two binomials (a+b)(c+d) defined in Unit 2?', ['ac', 'bd'], 'Binomial product pp.63, 74'],
            ['What are the procedures to find the Highest Common Factor (HCF) of numbers?', ['factors', '74'], 'HCF procedure p.74'],
            ['What is the theorem relating areas and sides of similar polygons (Theorem 4.5)?', ['similar', '13'], 'Theorem 4.5 similar polygons pp.137-138'],
            ['What is a cyclic quadrilateral or quadrilateral inscribed in a circle (Definition 5.7)?', ['circle', 'inscribed'], 'Definition 5.7 inscribed quadrilateral'],
            ['What topics are covered in Unit 1 and Unit 2 outcomes?', ['square', 'variable'], 'Multi-part Unit 1 & 2 outcomes'],
            ['Who invented the telescope in 1609?', ['not found in the textbook', 'could not find'], 'Negative test (not in Math book)'],
        ]
    ],
    36 => [
        'name' => 'Social Studies Grade 8 (Book 36)',
        'tests' => [
            ['What are internal forces that come from the inside of the earth?', ['folding', '2'], 'Unit 1 internal forces pp.2, 28'],
            ['Define evaporation and transpiration from the hydrological cycle.', ['liquid water', '20'], 'Evaporation & transpiration p.20'],
            ['What is convectional rainfall?', ['sun', '21'], 'Convectional rainfall p.21'],
            ['Where are Semitic languages spoken in Ethiopia?', ['tigray', '37'], 'Semitic languages p.37'],
            ['What is trade according to Unit 2?', ['buying', '47'], 'Trade definition p.47'],
            ['How is culture defined in the Unit 2 Summary?', ['symbols', '56'], 'Culture definition p.56'],
            ['What is a natural resource and what is conservation in the Unit 3 Summary?', ['physical supply', '78'], 'Natural resource & conservation p.78'],
            ['Which two European countries began the great discoveries in the 15th century, and what factors encouraged them?', ['portugal', 'spain'], 'Multi-part Great Discoveries p.114'],
            ['Define climate and climate change from the Unit 7 Summary, and list greenhouse gases.', ['average weather', '202'], 'Multi-part Climate & GHGs p.202'],
            ['What is the atomic number of uranium?', ['not found in the textbook', 'could not find'], 'Negative test (not in Social Studies book)'],
        ]
    ],
    37 => [
        'name' => 'General Science Grade 8 (Book 37)',
        'tests' => [
            ['What is a prefix in scientific measurements?', ['short hand', '10'], 'Scientific prefix definition p.10'],
            ['What is bottled gas (buta-gas) made of according to Unit 3?', ['propane', 'butane', '44'], 'Propane and butane p.44'],
            ['What is the objective of Experiment 3.7?', ['base', 'indicator', '60'], 'Experiment 3.7 base on indicators p.60'],
            ['How can gastritis be prevented according to Unit 4?', ['trigger foods', '105'], 'Gastritis prevention p.105'],
            ['How does the textbook define Environment in Unit 5 (5.1.1)?', ['biotic', 'abiotic', '130'], 'Environment biotic & abiotic p.130'],
            ['Why is soil important in agriculture according to Unit 5?', ['nutrients', '144'], 'Soil in agriculture p.144'],
            ['What are the two categories of planets in our solar system based on distance from the Sun, and list the planets in each?', ['terrestrial', 'jovian', '167'], 'Multi-part Terrestrial & Jovian planets p.167'],
            ['What is the temperature range on Mercury?', ['426', '167'], 'Mercury temperature p.167'],
            ['What are the learning outcomes of Unit 1 (Basics of Scientific Investigation)?', ['measurements', '1'], 'Unit 1 learning outcomes p.1'],
            ['Who won the 1998 FIFA World Cup?', ['not found in the textbook', 'could not find'], 'Negative test (not in General Science book)'],
        ]
    ],
    38 => [
        'name' => 'English for Ethiopia Grade 8 (Book 38)',
        'tests' => [
            ['What types of calendars are listed in Unit 2?', ['lunar', 'solar', '13'], 'Types of calendars p.13'],
            ['What is Ethiopia’s one fully endemic antelope species described in Unit 4 Lesson 6?', ['mountain nyala', '44'], 'Mountain Nyala p.44'],
            ['What are the expressions of farming activities listed in Unit 1 Lesson 9?', ['mixed farming', '10'], 'Farming activities p.10'],
            ['What does the suffix -able do and what does the prefix un- form in Unit 6 Lesson 8?', ['adjectives', 'opposite', '85'], 'Suffix -able and prefix un- p.85'],
            ['What are the Unit 1 objectives (Economic Activities in Rural Areas)?', ['economic activities', '1'], 'Unit 1 objectives p.1'],
            ['What are the Unit 8 objectives (Personal Hygiene)?', ['hygiene', '108'], 'Unit 8 objectives p.108'],
            ['In Unit 7 Lesson 8 (Helen’s story), where was Helen working and where did she catch a bus back to?', ['gambella', '105'], 'Helen story p.105'],
            ['What modal auxiliaries are practiced in Unit 3 Lesson 14?', ['must', '35'], 'Modal auxiliaries p.35'],
            ['What is the rule for forming the past tense of regular verbs ending in e or consonant+y in the Unit 10 Appendix?', ['ied', '143'], 'Regular verb spelling rules p.143'],
            ['What is the chemical formula for sulfuric acid?', ['not found in the textbook', 'could not find'], 'Negative test (not in English book)'],
        ]
    ]
];

$totalPassed = 0;
$totalTests = 0;

foreach ($suites as $bookId => $suite) {
    echo "\n==================================================\n";
    echo "BOOK {$bookId}: {$suite['name']}\n";
    echo "==================================================\n";
    $bookPassed = 0;
    foreach ($suite['tests'] as $idx => $t) {
        $totalTests++;
        [$q, $expectedAny, $label] = $t;
        echo "  Q" . ($idx + 1) . " ({$label}) ... ";
        $res = $mcp->callTool('ask_book', ['book_id' => $bookId, 'query' => $q]);
        usleep(1200000);
        $ans = mb_strtolower($res['answer'] ?? '', 'UTF-8');

        $ok = true;
        $missing = [];
        // For negative tests, any of the refusal phrases passes; for positive tests, all expected keywords must appear
        if (strpos($label, 'Negative test') !== false) {
            $ok = false;
            foreach ($expectedAny as $phrase) {
                if (mb_strpos($ans, mb_strtolower($phrase, 'UTF-8')) !== false) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) $missing = $expectedAny;
        } else {
            foreach ($expectedAny as $kw) {
                if (mb_strpos($ans, mb_strtolower($kw, 'UTF-8')) === false) {
                    $ok = false;
                    $missing[] = $kw;
                }
            }
        }

        if ($ok) {
            echo "\033[32mPASS\033[0m\n";
            $bookPassed++;
            $totalPassed++;
        } else {
            echo "\033[31mFAIL\033[0m (missing: " . implode(', ', $missing) . ")\n";
            echo "    Got: " . str_replace("\n", " ", substr($res['answer'] ?? '', 0, 180)) . "\n";
        }
    }
    echo "Book {$bookId} Score: {$bookPassed}/" . count($suite['tests']) . "\n";
}

echo "\n--------------------------------------------------\n";
echo "OVERALL MULTI-BOOK SCORE: {$totalPassed}/{$totalTests}\n";
exit($totalPassed === $totalTests ? 0 : 1);
