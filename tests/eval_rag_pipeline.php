<?php
/**
 * Verbose AI - Comprehensive Grounded RAG Automated Evaluation Suite
 * Tests all requirements against the real pipeline (Neon Postgres, pgvector, MCPService, AIService).
 */

require_once __DIR__ . '/../src/MCPService.php';
require_once __DIR__ . '/../src/Database.php';

$mcp = new MCPService();

// Find the English textbook book_id
$pdo = Database::getConnection();
if (!$pdo) {
    echo "FATAL: Database connection failed.\n";
    exit(1);
}

$stmt = $pdo->query("SELECT id, title FROM books WHERE title ILIKE '%Student Textbook (English)%' LIMIT 1");
$book = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$book) {
    echo "FATAL: English textbook not found in database.\n";
    exit(1);
}
$englishBookId = intval($book['id']);
echo "=== Verbose AI Automated Eval Suite ===\n";
echo "Target Textbook: {$book['title']} (Book ID: {$englishBookId})\n\n";

$passed = 0;
$total = 0;

function runTest($testNum, $name, callable $fn) {
    global $passed, $total;
    $total++;
    echo "Test {$testNum}: {$name} ... ";
    try {
        $result = $fn();
        if ($result === true) {
            echo "\033[32mPASS\033[0m\n";
            $passed++;
        } else {
            echo "\033[31mFAIL\033[0m\n  Details: {$result}\n";
        }
    } catch (Exception $e) {
        echo "\033[31mERROR\033[0m: " . $e->getMessage() . "\n";
    }
    usleep(1500000);
}

// --------------------------------------------------------------------------
// TEST 1: Five steps of ethical decision making (p.9)
// --------------------------------------------------------------------------
runTest(1, "Five steps of ethical decision making (p.9)", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'What are the five steps of ethical decision making in the textbook?'
    ]);

    $ans = strtolower($res['answer'] ?? '');
    
    // Assert 5 steps are present
    $hasProblem = (strpos($ans, 'central ethical problem') !== false || strpos($ans, 'identify the problem') !== false);
    $hasStakeholders = (strpos($ans, 'stakeholder') !== false);
    $hasSolutions = (strpos($ans, 'solution') !== false);
    $hasEvaluate = (strpos($ans, 'evaluate') !== false);
    $hasSelect = (strpos($ans, 'select') !== false || strpos($ans, 'justify') !== false);

    if (!$hasProblem) return "Missing 'identify the central ethical problem'";
    if (!$hasStakeholders) return "Missing 'identify stakeholders and their interests'";
    if (!$hasSolutions) return "Missing 'possible solutions to the dilemma'";
    if (!$hasEvaluate) return "Missing 'evaluate each solution'";
    if (!$hasSelect) return "Missing 'select and justify the best solution'";

    // Assert NO "take action and reflect" step
    if (strpos($ans, 'take action') !== false || strpos($ans, 'action and reflect') !== false) {
        return "Must NOT contain 'take action and reflect' step";
    }

    // Assert cited printed page 9
    if (strpos($ans, '9') === false && strpos($ans, 'page 9') === false) {
        return "Expected citation of printed page 9";
    }

    return true;
});

// --------------------------------------------------------------------------
// TEST 2: Moral virtue nature, traits & categories (p.6, p.11)
// --------------------------------------------------------------------------
runTest(2, "Moral virtue is not natural, virtuous traits, two categories (pp.6, 11)", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'According to the textbook, is moral virtue natural, what are the traits of a virtuous person, and what are the two categories of virtue ethics?'
    ]);

    $ans = strtolower($res['answer'] ?? '');

    $notNatural = (strpos($ans, 'not natural') !== false || strpos($ans, 'does not come naturally') !== false || strpos($ans, 'learned') !== false || strpos($ans, 'acquired') !== false);
    $practice = (strpos($ans, 'practice') !== false || strpos($ans, 'imitation') !== false);
    $traits = (strpos($ans, 'virtue') !== false && (strpos($ans, 'no vice') !== false || strpos($ans, 'vices') !== false || strpos($ans, 'good') !== false));
    $twoCategories = (strpos($ans, 'moral') !== false && strpos($ans, 'civic') !== false);

    if (!$notNatural) return "Missing statement that moral virtue is NOT natural";
    if (!$practice) return "Missing 'practice and imitation'";
    if (!$traits) return "Missing virtuous person character traits (virtues / no vices)";
    if (!$twoCategories) return "Missing two categories: moral virtues and civic virtues";

    return true;
});

// --------------------------------------------------------------------------
// TEST 3: Virtue ethics development and emphasis (p.8)
// --------------------------------------------------------------------------
runTest(3, "Virtue ethics developed by Aristotle & emphasizes character (p.8)", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'Who developed virtue ethics and what does it emphasise according to the textbook?'
    ]);

    $ans = strtolower($res['answer'] ?? '');

    $hasAristotle = (strpos($ans, 'aristotle') !== false || strpos($ans, 'greek') !== false);
    $hasCharacter = (strpos($ans, 'character') !== false);
    $dutiesOrConsequences = (strpos($ans, 'dut') !== false || strpos($ans, 'rule') !== false || strpos($ans, 'consequence') !== false);

    if (!$hasAristotle) return "Missing Aristotle or ancient Greek thinkers";
    if (!$hasCharacter) return "Missing emphasis on character";
    if (!$dutiesOrConsequences) return "Missing contrast with duties, rules, or consequences";

    return true;
});

// --------------------------------------------------------------------------
// TEST 4: Unitary state power distribution (p.48)
// --------------------------------------------------------------------------
runTest(4, "Unitary state: centre delegates powers at will, no vertical division (p.48)", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'How does a unitary state distribute power according to the textbook?'
    ]);

    $ans = strtolower($res['answer'] ?? '');

    $delegatesOrWill = (strpos($ans, 'delegate') !== false || strpos($ans, 'will') !== false || strpos($ans, 'centre') !== false || strpos($ans, 'center') !== false);
    $noVertical = (strpos($ans, 'vertical') !== false || strpos($ans, 'division') !== false || strpos($ans, 'central') !== false);

    if (!$delegatesOrWill) return "Missing centre delegation of powers";
    if (!$noVertical) return "Missing vertical division of power discussion";

    return true;
});

// --------------------------------------------------------------------------
// TEST 5: FDRE Constitution Article 52 reserved powers (p.54)
// --------------------------------------------------------------------------
runTest(5, "FDRE Constitution Art. 52: powers not expressly given are reserved to States (p.54)", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'What does Article 52 of the FDRE Constitution state about powers in the textbook?'
    ]);

    $ans = strtolower($res['answer'] ?? '');

    $has52 = (strpos($ans, '52') !== false || strpos($ans, 'article 52') !== false);
    $reservedToStates = (strpos($ans, 'reserved') !== false && (strpos($ans, 'state') !== false || strpos($ans, 'regional') !== false));

    if (!$has52) return "Missing Article 52 reference";
    if (!$reservedToStates) return "Missing powers reserved to the States";

    return true;
});

// --------------------------------------------------------------------------
// TEST 6: Montevideo Convention components of a state (p.71)
// --------------------------------------------------------------------------
runTest(6, "Montevideo Convention: population, territory, government, sovereignty (p.71)", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'What are the four components of a state according to the Montevideo Convention in the textbook?'
    ]);

    $ans = strtolower($res['answer'] ?? '');

    $hasPop = (strpos($ans, 'population') !== false);
    $hasTerr = (strpos($ans, 'territory') !== false);
    $hasGov = (strpos($ans, 'government') !== false);
    $hasSov = (strpos($ans, 'sovereignty') !== false);

    if (!$hasPop) return "Missing 'population'";
    if (!$hasTerr) return "Missing 'territory'";
    if (!$hasGov) return "Missing 'government'";
    if (!$hasSov) return "Missing 'sovereignty'";

    return true;
});

// --------------------------------------------------------------------------
// TEST 7: Indigenous institutions (Gada, Luwa, Awassiya, Kanchi) (pp.109-110)
// --------------------------------------------------------------------------
runTest(7, "Gada = Oromo, Luwa = Sidama, Awassiya = Wolaita, Kanchi = Wejerat (pp.109-110)", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'Which societies or ethnic groups practice Gada, Luwa, Awassiya, and Kanchi according to the textbook?'
    ]);

    $ans = strtolower($res['answer'] ?? '');

    $hasGada = (strpos($ans, 'gada') !== false && strpos($ans, 'oromo') !== false);
    $hasLuwa = (strpos($ans, 'luwa') !== false && strpos($ans, 'sidama') !== false);
    $hasAwassiya = (strpos($ans, 'awassiya') !== false && strpos($ans, 'wolaita') !== false);
    $hasKanchi = (strpos($ans, 'kanchi') !== false && (strpos($ans, 'wejerat') !== false || strpos($ans, 'wejer') !== false));

    if (!$hasGada) return "Missing Gada = Oromo association";
    if (!$hasLuwa) return "Missing Luwa = Sidama association";
    if (!$hasAwassiya) return "Missing Awassiya = Wolaita association";
    if (!$hasKanchi) return "Missing Kanchi = Wejerat association";

    return true;
});

// --------------------------------------------------------------------------
// TEST 8: Cross-lingual retrieval (Task B)
// --------------------------------------------------------------------------
runTest(8, "Cross-lingual retrieval (English query -> Amharic chunks and vice versa)", function() use ($mcp, $englishBookId) {
    // 1. English query on Amharic Book 30
    $amharicRes = $mcp->callTool('search_passages', [
        'book_id' => 30,
        'query' => 'Is moral virtue natural or learned?'
    ]);
    if (empty($amharicRes) || !isset($amharicRes[0]['similarity'])) {
        return "English query on Amharic Book 30 returned no results";
    }
    if ($amharicRes[0]['similarity'] < 0.55) {
        return "English query on Amharic Book 30 similarity too low: " . $amharicRes[0]['similarity'];
    }

    // 2. Amharic query on English Book 33
    $englishRes = $mcp->callTool('search_passages', [
        'book_id' => $englishBookId,
        'query' => 'የሞራል በጎነት በተፈጥሮ ይገኛል ወይስ በልምምድ?'
    ]);
    if (empty($englishRes) || !isset($englishRes[0]['similarity'])) {
        return "Amharic query on English Book 33 returned no results";
    }
    if ($englishRes[0]['similarity'] < 0.55) {
        return "Amharic query on English Book 33 similarity too low: " . $englishRes[0]['similarity'];
    }

    return true;
});

// --------------------------------------------------------------------------
// TEST 9: Negative test (query not in textbook -> not found in the textbook)
// --------------------------------------------------------------------------
runTest(9, "Negative test: 'What is the capital of Peru?' -> 'not found in the textbook'", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'What is the capital of Peru?'
    ]);

    $ans = strtolower($res['answer'] ?? '');
    $notFound = (strpos($ans, 'not found in the textbook') !== false ||
                 strpos($ans, 'could not find this in the textbook') !== false ||
                 strpos($ans, 'not in the textbook') !== false ||
                 strpos($ans, 'not mentioned in the textbook') !== false);

    if (!$notFound) {
        return "Expected refusal 'not found in the textbook', got: '{$res['answer']}'";
    }

    return true;
});

// --------------------------------------------------------------------------
// TEST 10: Step 4.1 - "What is civic virtue?" (p.28)
// --------------------------------------------------------------------------
runTest(10, "What is civic virtue? -> cites p.28, 'reasoned devotion or commitment', democracy, not p.11", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'What is civic virtue?'
    ]);

    $ans = strtolower($res['answer'] ?? '');
    if (strpos($ans, 'reasoned devotion') === false && strpos($ans, 'commitment') === false) {
        return "Missing definition quote: 'reasoned devotion or commitment'";
    }
    if (strpos($ans, 'democracy') === false && strpos($ans, 'public good') === false) {
        return "Missing reference to participation in democracy for private/public good";
    }
    if (strpos($ans, '28') === false && strpos($ans, 'page 28') === false) {
        return "Expected citation of printed page 28";
    }
    if (preg_match('/civic\s+virtue[^\.\n]*?(?:healthy functioning|community at large)[^\.\n]*?\[page\s*11\]/i', $ans)) {
        return "Must NOT cite page 11 as definition of civic virtue";
    }

    return true;
});

// --------------------------------------------------------------------------
// TEST 11: Step 4.2 - "Give examples of civic virtues" (p.29, p.30)
// --------------------------------------------------------------------------
runTest(11, "Give examples of civic virtues -> includes paying fair taxes (p.29) and staying informed (p.30)", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'Give examples of civic virtues according to the textbook.'
    ]);

    $ans = strtolower($res['answer'] ?? '');
    $hasTaxes = (strpos($ans, 'tax') !== false && (strpos($ans, '29') !== false || strpos($ans, 'fair') !== false));
    $hasInformed = (strpos($ans, 'informed') !== false && (strpos($ans, '30') !== false || strpos($ans, 'news') !== false));

    if (!$hasTaxes) return "Missing 'paying fair taxes' (p.29)";
    if (!$hasInformed) return "Missing 'staying informed' (p.30)";

    return true;
});

// --------------------------------------------------------------------------
// TEST 12: Step 4.3 - "What two categories is virtue ethics divided into?"
// --------------------------------------------------------------------------
runTest(12, "Two categories: moral and civic virtues (p.11); moral = live reasonably (p.11); civic = p.28", function() use ($mcp, $englishBookId) {
    $res = $mcp->callTool('ask_book', [
        'book_id' => $englishBookId,
        'query' => 'What two categories is virtue ethics divided into? Define each category according to the textbook.'
    ]);

    $ans = strtolower($res['answer'] ?? '');
    if (strpos($ans, 'moral') === false || strpos($ans, 'civic') === false) {
        return "Must name both moral and civic virtues";
    }
    if (strpos($ans, '11') === false) {
        return "Expected citation of page 11 for categories and moral virtues";
    }
    if (strpos($ans, '28') === false) {
        return "Expected citation of page 28 for civic virtues definition";
    }
    if (strpos($ans, 'reasonably') === false && strpos($ans, 'community') === false) {
        return "Expected moral virtues definition (living reasonably in community/region/world)";
    }
    if (strpos($ans, 'reasoned devotion') === false && strpos($ans, 'commitment') === false) {
        return "Expected civic virtues definition from p.28 ('reasoned devotion or commitment')";
    }

    return true;
});

echo "\n--------------------------------------------------\n";
echo "SUMMARY: {$passed}/{$total} tests passed.\n";

if ($passed === $total) {
    echo "\033[32mALL TESTS PASSED SUCCESSFULLY!\033[0m\n";
    exit(0);
} else {
    echo "\033[31mSOME TESTS FAILED!\033[0m\n";
    exit(1);
}
