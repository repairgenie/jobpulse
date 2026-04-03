<?php
namespace App;

class JobScraper {
    private $proxyUrl;

    public function __construct($proxyUrl = null) {
        $this->proxyUrl = $proxyUrl;
    }

    public function searchJobs($keywords, $location, $remote = false, $hybrid = false) {
        // Build search parameters
        $searchTerms = array_filter(array_map('trim', explode(' ', $keywords)));
        if ($remote) $searchTerms[] = 'remote';
        if ($hybrid) $searchTerms[] = 'hybrid';
        $query = implode('+', array_map('urlencode', $searchTerms));
        $locQuery = urlencode($location);

        $jobs = [];

        // Scraping implementation utilizing RemoteOK's open API endpoints as a real-world proxy capable fallback
        $url = "https://remoteok.com/api";

        // Construct query parameters
        // RemoteOK doesn't reliably return data if "remote" is in the tags array
        $apiSearchTerms = array_filter($searchTerms, function($t) { return strtolower($t) !== 'remote'; });
        $url .= "?tags=" . urlencode(implode(',', $apiSearchTerms));

        try {
            $response = $this->fetchWithProxy($url);
            $data = json_decode($response, true);

            if (is_array($data)) {
                // Remove the first legal notice element that RemoteOK returns
                array_shift($data);

                foreach ($data as $item) {
                    // Filter by location if specified and not explicitly remote
                    $itemLocation = strtolower($item['location'] ?? '');
                    $locQueryLower = strtolower($location);

                    if (!empty($location) && !$remote && strpos($itemLocation, $locQueryLower) === false && $itemLocation !== 'worldwide' && $itemLocation !== 'anywhere') {
                        continue;
                    }

                    $jobs[] = [
                        'id' => 'scraped_' . ($item['id'] ?? uniqid()),
                        'title' => $item['position'] ?? 'Unknown Position',
                        'company' => ['display_name' => $item['company'] ?? 'Unknown Company'],
                        'location' => ['display_name' => $item['location'] ?? 'Remote'],
                        'description' => strip_tags($item['description'] ?? ''),
                        'redirect_url' => $item['apply_url'] ?? $item['url'] ?? '#'
                    ];
                }
            }
        } catch (\Exception $e) {
            // Log or handle proxy/scrape error
            throw new \Exception("Scraping failed: " . $e->getMessage());
        }

        return array_slice($jobs, 0, 15);
    }

    public function fetchWithProxy($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JobPulse Scraper/1.0 (Contact: admin@jobpulse.ai)');

        if (!empty($this->proxyUrl)) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxyUrl);
        }

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new \Exception('Scraper error: ' . curl_error($ch));
        }

        curl_close($ch);
        return $response;
    }
}
