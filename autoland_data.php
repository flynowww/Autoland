<?php
define('WSDL_URL', "https://application.lionair.com/sl/Webservice/Epf.asmx?WSDL");
define('USER', 'SL!@#$');
define('PASS', 'SL*()');
define('FLIGHT_DATE', '2025-01-02');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'aircraft_autoland');

include("config.php.inc");

function createSoapClient($wsdl) {
    try {
        return new SoapClient($wsdl, array('trace' => 1, 'exceptions' => 1));
    } catch (SoapFault $fault) {
        error_log("SOAP Client Error: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})");
        echo "An error occurred while creating the SOAP client. Please try again later.";
        exit;
    }
}

function createXmlPostString($user, $pass, $flightDate) {
    return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:app="https://application.lionair.com">
   <soap:Header/>
   <soap:Body>
      <app:EPF>
         <app:User>' . htmlspecialchars($user) . '</app:User>
         <app:Pass>' . htmlspecialchars($pass) . '</app:Pass>
         <app:FlightDate>' . htmlspecialchars($flightDate) . '</app:FlightDate>
      </app:EPF>
   </soap:Body>
</soap:Envelope>';
}

function xmlPostStringToData($xmlString) {
    $xml = simplexml_load_string($xmlString);
    if ($xml === false) {
        throw new Exception("Failed to parse XML string.");
    }

    $namespaces = $xml->getNamespaces(true);
    if (!isset($namespaces['soap'])) {
        throw new Exception("SOAP namespace 'soap' not found in the XML string.");
    }

    $body = $xml->children($namespaces['soap'])->Body;
    if (isset($namespaces['app']) && isset($body->children($namespaces['app'])->EPF)) {
        $epf = $body->children($namespaces['app'])->EPF;

        return [
            'User' => (string) $epf->User,
            'Pass' => (string) $epf->Pass,
            'FlightDate' => (string) $epf->FlightDate,
        ];
    } else {
        throw new Exception("EPF element not found in the XML string.");
    }
}

function traceXmlProperties($xml, $indent = 0) {
    // Iterate over each property in the SimpleXMLElement object
    foreach ($xml as $key => $value) {
        // Print the current element's name with indentation
        echo str_repeat('  ', $indent) . "Element: $key\n";

        // Print attributes if they exist
        $attributes = $value->attributes();
        if ($attributes) {
            echo str_repeat('  ', $indent + 1) . "Attributes:\n";
            foreach ($attributes as $attrName => $attrValue) {
                echo str_repeat('  ', $indent + 2) . "$attrName => $attrValue\n";
            }
        }

        // Print text content if it exists
        $textContent = trim((string) $value);
        if (!empty($textContent)) {
            echo str_repeat('  ', $indent + 1) . "Text Content: $textContent\n";
        }

        // Recursively trace child elements
        if ($value->count() > 0) {
            traceXmlProperties($value, $indent + 1);
        }
    }
}

function xmlStringToArray($xmlString) {
    // Normalize the XML string by removing excessive whitespace
    $xmlString = trim(preg_replace('/\s+/', ' ', $xmlString));

    // Initialize variables
    $result = [];
    $stack = [];
    $current = &$result;

    // Regular expression to match XML tags, attributes, and content
    $pattern = '/<([a-zA-Z0-9_:]+)(\s+[^>]*?)?(\/?>)|<\/([a-zA-Z0-9_:]+)\s*>|([^<]+)/';

    // Match all parts of the XML string
    preg_match_all($pattern, $xmlString, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        if (!empty($match[1])) {
            // Opening tag (e.g., <tag>)
            $tagName = $match[1];
            $attributes = [];

            // Parse attributes if present
            if (!empty($match[2])) {
                preg_match_all('/([a-zA-Z0-9_:]+)="([^"]*)"/', $match[2], $attrMatches, PREG_SET_ORDER);
                foreach ($attrMatches as $attrMatch) {
                    $attributes[$attrMatch[1]] = $attrMatch[2];
                }
            }

            // Create a new array for this tag
            $newElement = [
                '@attributes' => $attributes,
                '@content' => [],
                '@children' => []
            ];

            // Push current context onto the stack
            $stack[] = &$current;

            // Add the new element to the current context
            if (!isset($current[$tagName])) {
                $current[$tagName] = [];
            }
            $current[$tagName][] = $newElement;
            $current = &$current[$tagName][count($current[$tagName]) - 1]['@children'];

            // Handle self-closing tags
            if (substr($match[3], -1) === '/') {
                array_pop($stack);
                $current = &$stack[count($stack) - 1];
            }
        } elseif (!empty($match[4])) {
            // Closing tag (e.g., </tag>)
            array_pop($stack);
            $current = &$stack[count($stack) - 1];
        } elseif (!empty($match[5])) {
            // Text content
            $current['@content'].= @trim($match[5]);
        }
    }

    return $result;
}

// Helper function to access values by key
function getValueByKey($array, $keyPath) {
    $keys = explode('.', $keyPath);
    $current = $array;

    foreach ($keys as $key) {
        if (is_array($current) && isset($current[$key])) {
            $current = $current[$key];
        } else {
            return null; // Key not found
        }
    }

    return $current;
}
 



function handleResponse($response) {
 
    // Write the original $response string to a text file
    $filePath = 'response.txt'; // File path
    if (file_put_contents($filePath, $response) !== false) {
        echo "The response has been successfully written to '$filePath'.\n";
    } else {
        echo "Failed to write the response to the file.\n";
    }
echo "<pre>";
    $xx = xmlStringToArray($response);
    //
   
    // Ensure the key exists before accessing it
    if (isset($xx['soap:Envelope'][0]['soap:Body'][0]['EPFResponse'][0]['EPFResult'][0])) {
        print_r($xx['soap:Envelope'][0]['soap:Body'][0]['EPFResponse'][0]['EPFResult'][0]);
    } else {
        echo "Key not found in the response array.";
    }
    
// Access values by key
echo "Name of first item: " . getValueByKey($xx, 'root.item.0.name.@content') . "\n";
echo "Description of second item: " . getValueByKey($xx, 'root.item.1.description.@content') . "\n";

echo "</pre>";
    
    $xml = simplexml_load_string($response);
    // var_dump($xml);
    // traceXmlProperties($xml);
    if ($xml === false) {
        throw new Exception("Failed to parse XML response.");
    }
    $namespaces = $xml->getNamespaces(true);
    if (!isset($namespaces['soap'])) {
        throw new Exception("SOAP namespace 'soap' not found in the response.");
    }
    $body = $xml->children($namespaces['soap'])->Body;
   //echo  "</pre><br>".var_dump($body). "</pre><br>";
    if (isset($namespaces['app']) && isset($body->children($namespaces['app'])->EPFResponse)) {
        $epfResponse = $body->children($namespaces['app'])->EPFResponse;
        $result = $epfResponse->EPFResult;

        $data = [
            'Aircraft_autoland_departure_airport' => htmlspecialchars($result->Aircraft_autoland_departure_airport),
            'Aircraft_autoland_arrival_airport' => htmlspecialchars($result->Aircraft_autoland_arrival_airport),
            'Aircraft_autoland_date_of_flight' => htmlspecialchars($result->Aircraft_autoland_date_of_flight),
            'Aircraft_autoland_flight_number' => htmlspecialchars($result->Aircraft_autoland_flight_number),
            'Aircraft_autoland_aircraft' => htmlspecialchars($result->Aircraft_autoland_aircraft),
            'Aircraft_autoland_wx' => htmlspecialchars($result->Aircraft_autoland_wx),
            'Aircraft_autoland_wind' => htmlspecialchars($result->Aircraft_autoland_wind),
            'Aircraft_autoland_runway' => htmlspecialchars($result->Aircraft_autoland_runway),
            'Aircraft_autoland_pic' => htmlspecialchars($result->Aircraft_autoland_pic),
            'Aircraft_autoland_sic' => htmlspecialchars($result->Aircraft_autoland_sic),
            'Aircraft_autoland_remark' => htmlspecialchars($result->Aircraft_autoland_remark),
            'recordinfo' => 'EPF'
        ];

        saveToDatabase($data);
    } else {
        echo "EPF Response element not found in the response.<br>";
        echo "Raw Response: <pre>" . nl2br(htmlspecialchars($response)) . "</pre><br>";
    }
}

function saveToDatabase($data) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO aircraft_autoland (aircraft_autoland_dep, aircraft_autoland_arr, aircraft_autoland_dateofflight, aircraft_autoland_flightno, aircraft_autoland_aircraft, aircraft_autoland_wx, aircraft_autoland_wind, aircraft_autoland_runway, aircraft_autoland_pic, aircraft_autoland_sic, aircraft_autoland_remark, aircraft_autoland_recordinfo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssss", $data['Aircraft_autoland_departure_airport'], $data['Aircraft_autoland_arrival_airport'], $data['Aircraft_autoland_date_of_flight'], $data['Aircraft_autoland_flight_number'], $data['Aircraft_autoland_aircraft'], $data['Aircraft_autoland_wx'], $data['Aircraft_autoland_wind'], $data['Aircraft_autoland_runway'], $data['Aircraft_autoland_pic'], $data['Aircraft_autoland_sic'], $data['Aircraft_autoland_remark'], $data['recordinfo']);

    if ($stmt->execute()) {
        echo "Data saved successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}

$client = createSoapClient(WSDL_URL);
$xml_post_string = createXmlPostString(USER, PASS, FLIGHT_DATE);

try {
    $response = $client->__doRequest($xml_post_string, WSDL_URL, 'EPF', SOAP_1_2);
    if ($response) {
        echo "Raw Response: <pre>" . nl2br(htmlspecialchars($response)) . "</pre><br>";
        handleResponse($response);
    } else {
        echo "No response received.";
    }
} catch (SoapFault $fault) {
    error_log("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})");
    echo "An error occurred. Please try again later.";
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "An error occurred. Please try again later.";
}

// Example usage
$xml_post_string = createXmlPostString(USER, PASS, FLIGHT_DATE);
$data = xmlPostStringToData($xml_post_string);
print_r($data);
?>