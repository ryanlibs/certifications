<?php

$apiUrl = "https://sololearn-api.replit.app/?profile=33173273"; # repo: https://github.com/ryanlibs/sololearn-profile-fetcher

function createGitHubIssue($title, $body) {
    $repoOwner = 'ryanlibs';
    $repoName = 'certifications';
    $token = getenv('GITHUB_TOKEN');

    if (!$token) {
        echo "GITHUB_TOKEN is not set. Ensure it is properly configured in the workflow.\n";
        exit(1);
    }

    $url = "https://api.github.com/repos/$repoOwner/$repoName/issues";
    $data = json_encode(['title' => $title, 'body' => $body]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json",
        "User-Agent: GitHub Actions"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 201) {
        $error = curl_error($ch);
        echo "Failed to create GitHub issue. HTTP Code: $httpCode, Error: $error\n";
    } else {
        echo "GitHub issue created successfully. Response: $response\n";
    }

    curl_close($ch);
}

$response = file_get_contents($apiUrl);
if ($response === false) {
    $errorMessage = "Error fetching data from the API.";
    echo $errorMessage . "\n";
    createGitHubIssue("API Fetch Error", $errorMessage);
    exit(1);
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $errorMessage = "Error decoding JSON: " . json_last_error_msg();
    echo $errorMessage . "\n";
    createGitHubIssue("JSON Decoding Error", $errorMessage);
    exit(1);
}

// Extract certificates
if (!isset($data['certificates']) || !is_array($data['certificates'])) {
    $errorMessage = "No certificates found in the API response.";
    echo $errorMessage . "\n";
    createGitHubIssue("No Certificates Found", $errorMessage);
    exit(1);
}

$fetchedCertificates = $data['certificates'];

// Add manually-added certificates
$manualCertificates = [
    [
        'name' => '13th IT Skills Olympics | Cybersecurity',
        'iconURL' => 'cert/icon/13thITSkillsOlympics.jpg',
        'startDate' => '2024-11-22',
        'imageUrl' => 'cert/certificates/13thITSkillsOlympics.png',
    ],
    [
        'name' => 'Java Fundamentals',
        'iconURL' => 'cert/icon/java_icon.png',
        'startDate' => '2023-06-23',
        'imageUrl' => 'cert/certificates/java_fundamentals2.pdf',
    ],
    [
        'name' => 'Java Fundamentals',
        'iconURL' => 'cert/icon/java_icon.png',
        'startDate' => '2023-01-26',
        'imageUrl' => 'cert/certificates/java_fundamentals1.pdf',
    ],
];

// Merge and sort certificates
$allCertificates = array_merge($fetchedCertificates, $manualCertificates);
usort($allCertificates, function ($a, $b) {
    return strtotime($b['startDate']) - strtotime($a['startDate']);
});

// Generate table content
$newTable = '<table>
  <thead>
    <tr>
      <th>Course</th>
      <th>Certificate</th>
      <th>Details</th>
    </tr>
  </thead>
  <tbody>';

foreach ($allCertificates as $certificate) {
    $name = htmlspecialchars($certificate['name']);
    $iconUrl = !empty($certificate['iconURL']) ? htmlspecialchars($certificate['iconURL']) : null;
    $startDate = htmlspecialchars(substr($certificate['startDate'], 0, 10));

    // Reset default values
    $pdfUrl = null;
    $jpgUrl = null;
    $pdfEmbed = null;
    $shareUrl = null;

    // Handle fetched certificates
    if (isset($certificate['url']) && preg_match('/\/certificates\/([A-Z]{2}-[^\/]+)/', $certificate['url'], $matches)) {
        $certificateCode = $matches[1];
        $pdfUrl = "https://www.sololearn.com/Certificate/$certificateCode/pdf/";
        $jpgUrl = "https://www.sololearn.com/Certificate/$certificateCode/jpg/";
        $shareUrl = htmlspecialchars($certificate['shareUrl']);
    }

    // Handle manual certificates
    if (isset($certificate['imageUrl'])) {
        $fileExtension = pathinfo($certificate['imageUrl'], PATHINFO_EXTENSION);
        echo "Processing file: " . htmlspecialchars($certificate['imageUrl']) . " (Extension: $fileExtension)\n"; // Debugging output
        if (strtolower($fileExtension) === 'pdf') {
            $pdfUrl = htmlspecialchars($certificate['imageUrl']);
            $pdfEmbed = "<embed src=\"$pdfUrl\" width=\"450\" height=\"600\" type=\"application/pdf\">";
            echo "PDF Embed set for: " . htmlspecialchars($certificate['imageUrl']) . "\n"; // Debugging output
        } else {
            $jpgUrl = htmlspecialchars($certificate['imageUrl']);
            echo "Image URL set for: " . htmlspecialchars($certificate['imageUrl']) . "\n"; // Debugging output
        }
    }

    $newTable .= "<tr>
      <td align=\"center\">";

    // Add icon if available
    if ($iconUrl) {
        $newTable .= "<img src=\"$iconUrl\" alt=\"$name Icon\" width=\"100\"><br>";
    }

    $newTable .= "<strong>$name</strong>
      </td>
      <td align=\"center\">";

    // Render embedded PDF or image preview
    if (!empty($pdfEmbed)) {
        $newTable .= $pdfEmbed;
    } elseif (!empty($jpgUrl)) {
        $newTable .= "<img src=\"$jpgUrl\" alt=\"$name Certificate\" width=\"450\">";
    }

    // Add fallback PDF link if not embedded
    if ($pdfUrl && empty($pdfEmbed)) {
        $newTable .= "<br><a href=\"$pdfUrl\" target=\"_blank\">📄 View PDF Certificate</a>";
    }

    $newTable .= "</td>
      <td>
        <ul>
          <li><strong>Date:</strong> $startDate</li>";

    if ($shareUrl) {
        $newTable .= "<li><a href=\"$shareUrl\" target=\"_blank\">View Certificate</a></li>";
    }

    $newTable .= "</ul>
      </td>
    </tr>";
}

$newTable .= '</tbody>
</table>';

// Update README.md
$readmePath = 'README.md';
$newReadmeContent = "# Certifications\n\n" . $newTable;

if (file_exists($readmePath)) {
    $currentContent = file_get_contents($readmePath);
    if (trim($currentContent) === trim($newReadmeContent)) {
        echo "No changes in certificates. README.md not updated.\n";
        exit;
    }
}

file_put_contents($readmePath, $newReadmeContent);
echo "README.md has been updated with the latest certificates.\n";

?>
