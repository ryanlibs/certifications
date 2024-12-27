<?php

$apiUrl = "https://sololearn-api.replit.app/?profile=test"; # repo: https://github.com/ryanlibs/sololearn-profile-fetcher

function createGitHubIssue($title, $body) {
    $repoOwner = 'ryanlibs';
    $repoName = 'certifications'; 
    $token = getenv('GITHUB_TOKEN');

    $url = "https://api.github.com/repos/$repoOwner/$repoName/issues";
    $data = json_encode(['title' => $title, 'body' => $body]);

    $headers = [
        "Authorization: Bearer $token",
        "Content-Type: application/json",
        "User-Agent: GitHub Actions"
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $data
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        echo "Failed to create GitHub issue.\n";
    } else {
        echo "GitHub issue created successfully.\n";
    }
}

$response = file_get_contents($apiUrl);
if ($response === false) {
    $errorMessage = "Error fetching data from the API.";
    echo $errorMessage . "\n";
    createGitHubIssue("API Fetch Error", $errorMessage);
    exit(1); // Stop the workflow
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $errorMessage = "Error decoding JSON: " . json_last_error_msg();
    echo $errorMessage . "\n";
    createGitHubIssue("JSON Decoding Error", $errorMessage);
    exit(1); // Stop the workflow
}

// Extract certificates
if (!isset($data['certificates']) || !is_array($data['certificates'])) {
    $errorMessage = "No certificates found in the API response.";
    echo $errorMessage . "\n";
    createGitHubIssue("No Certificates Found", $errorMessage);
    exit(1); // Stop the workflow
}

$certificates = $data['certificates'];

// Sort certificates by startDate in descending order
usort($certificates, function ($a, $b) {
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

foreach ($certificates as $certificate) {
    $name = htmlspecialchars($certificate['name']);
    $iconUrl = htmlspecialchars($certificate['iconURL']);
    $startDate = htmlspecialchars(substr($certificate['startDate'], 0, 10)); // Extract YYYY-MM-DD

    // Extract certificate code using regex
    if (preg_match('/\/certificates\/([A-Z]{2}-[^\/]+)/', $certificate['url'], $matches)) {
        $certificateCode = $matches[1];
    } else {
        echo "Failed to extract certificate code for: " . htmlspecialchars($certificate['name']) . "\n";
        continue;
    }

    $pdfUrl = "https://www.sololearn.com/Certificate/$certificateCode/pdf/";
    $jpgUrl = "https://www.sololearn.com/Certificate/$certificateCode/jpg/";
    $shareUrl = htmlspecialchars($certificate['shareUrl']);

    $newTable .= "<tr>
      <td align=\"center\">
        <img src=\"$iconUrl\" alt=\"$name Icon\" width=\"100\"><br>
        <strong>$name</strong>
      </td>
      <td align=\"center\">
        <a href=\"$pdfUrl\" target=\"_blank\">📄 PDF</a><br>
        <img src=\"$jpgUrl\" alt=\"$name Certificate\" width=\"450\">
      </td>
      <td>
        <ul>
          <li><strong>Date:</strong> $startDate</li>
          <li><a href=\"$shareUrl\" target=\"_blank\">View Certificate</a></li>
        </ul>
      </td>
    </tr>";
}

$newTable .= '</tbody>
</table>';

// Add table to README.md
$readmePath = 'README.md';
$newReadmeContent = "# Certifications\n\n" . $newTable;

// Check if the content has changed
if (file_exists($readmePath)) {
    $currentContent = file_get_contents($readmePath);

    if (trim($currentContent) === trim($newReadmeContent)) {
        echo "No changes in certificates. README.md not updated.\n";
        exit; // Exit if there's no difference
    }
}

// Update README.md if the content has changed
file_put_contents($readmePath, $newReadmeContent);

echo "README.md has been updated with the latest certificates.\n";
?>
