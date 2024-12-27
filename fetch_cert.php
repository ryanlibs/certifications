<?php

$apiUrl = "https://sololearn-api.replit.app/?profile=33173273"; # repo: https://github.com/ryanlibs/sololearn-profile-fetcher

$response = file_get_contents($apiUrl);
if ($response === false) {
    die("Error fetching data from the API.");
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error decoding JSON: " . json_last_error_msg());
}

// Extract certificates
if (!isset($data['certificates']) || !is_array($data['certificates'])) {
    echo "No certificates found in the API response.\n";
    exit;
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
    if (preg_match('/\/certificates\/(CC-[^\/]+)/', $certificate['url'], $matches)) {
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
          <li><strong>Issued Date:</strong> $startDate</li>
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
