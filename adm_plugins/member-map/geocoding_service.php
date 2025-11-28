<?php
/**
 ***********************************************************************************************
 * Geocoding Service for Member Map Plugin
 *
 * Provides geocoding functionality using Nominatim (OpenStreetMap) or Google Maps API
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class GeocodingService
 * Handles geocoding of addresses to coordinates
 */
class GeocodingService
{
    /** @var string Geocoding service to use ('nominatim' or 'google') */
    private string $service;

    /** @var string Google API key (if using Google) */
    private string $googleApiKey;

    /** @var int Delay between requests in milliseconds */
    private int $requestDelay;

    /** @var int Timestamp of last request */
    private int $lastRequestTime = 0;

    /**
     * Constructor
     *
     * @param string $service Service to use ('nominatim' or 'google')
     * @param string $googleApiKey Google API key (only required for Google service)
     * @param int $requestDelay Delay between requests in milliseconds
     */
    public function __construct(string $service = 'nominatim', string $googleApiKey = '', int $requestDelay = 1000)
    {
        $this->service = $service;
        $this->googleApiKey = $googleApiKey;
        $this->requestDelay = max(1000, $requestDelay); // Minimum 1 second for Nominatim
    }

    /**
     * Geocode an address to get coordinates
     *
     * @param string $address Full address string
     * @return array|null Array with 'lat' and 'lng' keys, or null if geocoding failed
     */
    public function geocode(string $address): ?array
    {
        if (empty(trim($address))) {
            return null;
        }

        // Respect rate limiting
        $this->waitForRateLimit();

        if ($this->service === 'google' && !empty($this->googleApiKey)) {
            return $this->geocodeWithGoogle($address);
        }

        return $this->geocodeWithNominatim($address);
    }

    /**
     * Geocode using OpenStreetMap Nominatim
     *
     * @param string $address Address to geocode
     * @return array|null Coordinates or null
     */
    private function geocodeWithNominatim(string $address): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 0
        ]);

        $response = $this->makeRequest($url, [
            'User-Agent: Admidio Member Map Plugin (https://www.admidio.org)'
        ]);

        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);

        if (empty($data) || !isset($data[0]['lat']) || !isset($data[0]['lon'])) {
            return null;
        }

        return [
            'lat' => (float) $data[0]['lat'],
            'lng' => (float) $data[0]['lon']
        ];
    }

    /**
     * Geocode using Google Maps API
     *
     * @param string $address Address to geocode
     * @return array|null Coordinates or null
     */
    private function geocodeWithGoogle(string $address): ?array
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'address' => $address,
            'key' => $this->googleApiKey
        ]);

        $response = $this->makeRequest($url);

        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);

        if (
            !isset($data['status']) ||
            $data['status'] !== 'OK' ||
            empty($data['results'])
        ) {
            return null;
        }

        $location = $data['results'][0]['geometry']['location'];

        return [
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng']
        ];
    }

    /**
     * Make HTTP request
     *
     * @param string $url URL to request
     * @param array $headers Additional headers
     * @return string|null Response body or null on error
     */
    private function makeRequest(string $url, array $headers = []): ?string
    {
        $this->lastRequestTime = (int) (microtime(true) * 1000);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", array_merge([
                    'Accept: application/json',
                    'Accept-Language: en'
                ], $headers)),
                'timeout' => 10,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        return $response;
    }

    /**
     * Wait to respect rate limiting
     */
    private function waitForRateLimit(): void
    {
        if ($this->lastRequestTime > 0) {
            $elapsed = (int) (microtime(true) * 1000) - $this->lastRequestTime;
            $waitTime = $this->requestDelay - $elapsed;

            if ($waitTime > 0) {
                usleep($waitTime * 1000);
            }
        }
    }

    /**
     * Build address string from user profile fields
     *
     * @param \Admidio\Users\Entity\User $user User object
     * @param array $addressFields Array of profile field names
     * @return string Combined address string
     */
    public static function buildAddressFromUser(\Admidio\Users\Entity\User $user, array $addressFields): string
    {
        $parts = [];

        foreach ($addressFields as $fieldName) {
            $value = trim($user->getValue($fieldName));
            if (!empty($value)) {
                $parts[] = $value;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Check if user's address has changed compared to stored coordinates
     *
     * @param \Admidio\Users\Entity\User $user User object
     * @param array $addressFields Array of profile field names
     * @param string $latitudeField Latitude field name
     * @param string $longitudeField Longitude field name
     * @return bool True if address might have changed (coordinates missing or address updated)
     */
    public static function addressNeedsGeocoding(
        \Admidio\Users\Entity\User $user,
        array $addressFields,
        string $latitudeField,
        string $longitudeField
    ): bool {
        // Check if coordinates are missing
        $lat = trim($user->getValue($latitudeField));
        $lng = trim($user->getValue($longitudeField));

        if (empty($lat) || empty($lng)) {
            // Check if there's an address to geocode
            $address = self::buildAddressFromUser($user, $addressFields);
            return !empty($address);
        }

        return false;
    }

    /**
     * Get geocoding service name for display
     *
     * @return string Service name
     */
    public function getServiceName(): string
    {
        return $this->service === 'google' ? 'Google Maps' : 'OpenStreetMap Nominatim';
    }
}

/**
 * Class GeocodingBatchProcessor
 * Handles batch geocoding of multiple users
 */
class GeocodingBatchProcessor
{
    /** @var GeocodingService */
    private GeocodingService $geocodingService;

    /** @var \Admidio\Infrastructure\Database */
    private $db;

    /** @var \Admidio\Users\Entity\ProfileFields */
    private $profileFields;

    /** @var array Address field names */
    private array $addressFields;

    /** @var string Latitude field name */
    private string $latitudeField;

    /** @var string Longitude field name */
    private string $longitudeField;

    /** @var array Processing results */
    private array $results = [
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => []
    ];

    /**
     * Constructor
     *
     * @param GeocodingService $geocodingService
     * @param mixed $db Database instance
     * @param mixed $profileFields ProfileFields instance
     * @param array $addressFields Address field names
     * @param string $latitudeField Latitude field name
     * @param string $longitudeField Longitude field name
     */
    public function __construct(
        GeocodingService $geocodingService,
        $db,
        $profileFields,
        array $addressFields,
        string $latitudeField,
        string $longitudeField
    ) {
        $this->geocodingService = $geocodingService;
        $this->db = $db;
        $this->profileFields = $profileFields;
        $this->addressFields = $addressFields;
        $this->latitudeField = $latitudeField;
        $this->longitudeField = $longitudeField;
    }

    /**
     * Process a single user
     *
     * @param int $userId User ID to process
     * @param bool $forceUpdate Force update even if coordinates exist
     * @return bool True if successful
     */
    public function processUser(int $userId, bool $forceUpdate = false): bool
    {
        $user = new \Admidio\Users\Entity\User($this->db, $this->profileFields);
        $user->readDataById($userId);

        // Check if geocoding is needed
        if (!$forceUpdate && !GeocodingService::addressNeedsGeocoding(
            $user,
            $this->addressFields,
            $this->latitudeField,
            $this->longitudeField
        )) {
            $this->results['skipped']++;
            return true;
        }

        // Build address
        $address = GeocodingService::buildAddressFromUser($user, $this->addressFields);

        if (empty($address)) {
            $this->results['skipped']++;
            return true;
        }

        // Geocode
        $coordinates = $this->geocodingService->geocode($address);

        if ($coordinates === null) {
            $this->results['failed']++;
            $this->results['errors'][] = sprintf(
                'User %d: Could not geocode address "%s"',
                $userId,
                $address
            );
            return false;
        }

        // Save coordinates
        try {
            $user->setValue($this->latitudeField, (string) $coordinates['lat']);
            $user->setValue($this->longitudeField, (string) $coordinates['lng']);
            $user->save();

            $this->results['success']++;
            return true;
        } catch (\Throwable $e) {
            $this->results['failed']++;
            $this->results['errors'][] = sprintf(
                'User %d: Error saving coordinates - %s',
                $userId,
                $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Process multiple users
     *
     * @param array $userIds Array of user IDs
     * @param bool $forceUpdate Force update even if coordinates exist
     * @param callable|null $progressCallback Callback for progress updates
     */
    public function processUsers(array $userIds, bool $forceUpdate = false, ?callable $progressCallback = null): void
    {
        $total = count($userIds);
        $current = 0;

        foreach ($userIds as $userId) {
            $current++;
            $this->processUser($userId, $forceUpdate);

            if ($progressCallback !== null) {
                $progressCallback($current, $total, $this->results);
            }
        }
    }

    /**
     * Get processing results
     *
     * @return array Results array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Reset results
     */
    public function resetResults(): void
    {
        $this->results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];
    }
}
