<?php
/*
 * Lesbare Flugzeugtypen. adsb.lol liefert nur das ICAO-Kuerzel aus Doc 8643
 * (B38M, A20N, E75L), auf dem Panel soll stehen, was man kennt: 737 MAX 8,
 * A320neo, E175. Die Tabelle deckt Linienverkehr, Fracht, Geschaeftsreise,
 * Allgemeine Luftfahrt, Hubschrauber und das Militaer ab, das ueber Luxemburg
 * fliegt. Fehlt ein Kuerzel, bleibt es stehen. Jeder Name hat hoechstens 15
 * Zeichen, so breit sind die Zeilen neben dem Logo.
 *
 * Schreibweise wie beim Hersteller: A321neo mit kleinem neo, 737 MAX 8 ohne
 * Bindestrich. Der Server schreibt sonst alles gross, hier nicht.
 */

declare(strict_types=1);

const AIRCRAFT_TYPES = [
    // Airbus
    'A30B' => 'A300', 'A306' => 'A300-600', 'A3ST' => 'Beluga', 'A310' => 'A310',
    'A318' => 'A318', 'A319' => 'A319', 'A320' => 'A320', 'A321' => 'A321',
    'A19N' => 'A319neo', 'A20N' => 'A320neo', 'A21N' => 'A321neo',
    'A332' => 'A330-200', 'A333' => 'A330-300', 'A337' => 'Beluga XL', 'A338' => 'A330-800neo', 'A339' => 'A330-900neo',
    'A342' => 'A340-200', 'A343' => 'A340-300', 'A345' => 'A340-500', 'A346' => 'A340-600',
    'A359' => 'A350-900', 'A35K' => 'A350-1000', 'A388' => 'A380-800', 'A400' => 'A400M Atlas',
    'BCS1' => 'A220-100', 'BCS3' => 'A220-300',
    // Boeing
    'B712' => '717-200', 'B721' => '727-100', 'B722' => '727-200',
    'B732' => '737-200', 'B733' => '737-300', 'B734' => '737-400', 'B735' => '737-500', 'B736' => '737-600',
    'B737' => '737-700', 'B738' => '737-800', 'B739' => '737-900',
    'B37M' => '737 MAX 7', 'B38M' => '737 MAX 8', 'B39M' => '737 MAX 9', 'B3XM' => '737 MAX 10',
    'B741' => '747-100', 'B742' => '747-200', 'B743' => '747-300', 'B744' => '747-400', 'B748' => '747-8',
    'B74S' => '747SP', 'BLCF' => '747 Dreamlifter',
    'B752' => '757-200', 'B753' => '757-300', 'B762' => '767-200', 'B763' => '767-300', 'B764' => '767-400',
    'B772' => '777-200', 'B77L' => '777-200LR', 'B773' => '777-300', 'B77W' => '777-300ER', 'B778' => '777-8', 'B779' => '777-9',
    'B788' => '787-8', 'B789' => '787-9', 'B78X' => '787-10',
    'MD11' => 'MD-11', 'DC10' => 'DC-10', 'MD82' => 'MD-82', 'MD83' => 'MD-83', 'MD87' => 'MD-87', 'MD88' => 'MD-88', 'MD90' => 'MD-90',
    // Embraer
    'E120' => 'Brasilia', 'E135' => 'ERJ-135', 'E145' => 'ERJ-145', 'E170' => 'E170', 'E75L' => 'E175', 'E75S' => 'E175',
    'E190' => 'E190', 'E195' => 'E195', 'E290' => 'E190-E2', 'E295' => 'E195-E2',
    'E50P' => 'Phenom 100', 'E55P' => 'Phenom 300', 'E545' => 'Legacy 450', 'E550' => 'Legacy 500', 'E35L' => 'Legacy 600',
    // Bombardier, De Havilland Canada
    'CRJ1' => 'CRJ-100', 'CRJ2' => 'CRJ-200', 'CRJ7' => 'CRJ-700', 'CRJ9' => 'CRJ-900', 'CRJX' => 'CRJ-1000',
    'DH8A' => 'Dash 8-100', 'DH8B' => 'Dash 8-200', 'DH8C' => 'Dash 8-300', 'DH8D' => 'Dash 8-400',
    'DHC6' => 'Twin Otter', 'DHC7' => 'Dash 7',
    'CL30' => 'Challenger 300', 'CL35' => 'Challenger 350', 'CL60' => 'Challenger 600',
    'GLEX' => 'Global Express', 'GL5T' => 'Global 5000', 'GL6T' => 'Global 6000', 'GL7T' => 'Global 7500',
    'LJ35' => 'Learjet 35', 'LJ40' => 'Learjet 40', 'LJ45' => 'Learjet 45', 'LJ60' => 'Learjet 60', 'LJ75' => 'Learjet 75',
    // ATR, Fokker, BAe, Saab, Dornier und andere Regionalflugzeuge
    'AT43' => 'ATR 42-300', 'AT44' => 'ATR 42-400', 'AT45' => 'ATR 42-500', 'AT46' => 'ATR 42-600',
    'AT72' => 'ATR 72', 'AT73' => 'ATR 72-210', 'AT75' => 'ATR 72-500', 'AT76' => 'ATR 72-600',
    'F50' => 'Fokker 50', 'F70' => 'Fokker 70', 'F100' => 'Fokker 100',
    'B461' => 'BAe 146-100', 'B462' => 'BAe 146-200', 'B463' => 'BAe 146-300',
    'RJ70' => 'Avro RJ70', 'RJ85' => 'Avro RJ85', 'RJ1H' => 'Avro RJ100',
    'SF34' => 'Saab 340', 'SB20' => 'Saab 2000', 'D228' => 'Dornier 228', 'D328' => 'Dornier 328', 'J328' => '328JET',
    'JS31' => 'Jetstream 31', 'JS32' => 'Jetstream 32', 'JS41' => 'Jetstream 41', 'B190' => 'Beech 1900',
    'SW4' => 'Metroliner', 'L410' => 'Let L-410', 'SU95' => 'Superjet 100', 'C919' => 'C919',
    'C295' => 'C295', 'CN35' => 'CN-235',
    'AN12' => 'An-12', 'AN24' => 'An-24', 'AN26' => 'An-26', 'A124' => 'An-124 Ruslan',
    'IL76' => 'Il-76', 'IL96' => 'Il-96', 'T154' => 'Tu-154', 'T204' => 'Tu-204',
    // Geschaeftsreise
    'GLF2' => 'Gulfstream II', 'GLF3' => 'Gulfstream III', 'GLF4' => 'Gulfstream IV', 'GLF5' => 'Gulfstream V',
    'GLF6' => 'Gulfstream G650', 'G150' => 'Gulfstream G150', 'G280' => 'Gulfstream G280', 'GALX' => 'Gulfstream G200',
    'GA5C' => 'Gulfstream G500', 'GA6C' => 'Gulfstream G600', 'GA7C' => 'Gulfstream G700', 'GA8C' => 'Gulfstream G800',
    'F2TH' => 'Falcon 2000', 'F900' => 'Falcon 900', 'FA10' => 'Falcon 10', 'FA20' => 'Falcon 20', 'FA50' => 'Falcon 50',
    'FA6X' => 'Falcon 6X', 'FA7X' => 'Falcon 7X', 'FA8X' => 'Falcon 8X',
    'C500' => 'Citation I', 'C501' => 'Citation I SP', 'C510' => 'Cessna Mustang', 'C525' => 'CitationJet',
    'C25A' => 'Citation CJ2', 'C25B' => 'Citation CJ3', 'C25C' => 'Citation CJ4', 'C25M' => 'Citation M2',
    'C550' => 'Citation II', 'C551' => 'Citation II SP', 'C560' => 'Citation V', 'C56X' => 'Citation XLS',
    'C650' => 'Citation III', 'C680' => 'Cit. Sovereign', 'C68A' => 'Cit. Latitude', 'C700' => 'Cit. Longitude', 'C750' => 'Citation X',
    'PRM1' => 'Premier I', 'BE40' => 'Beechjet 400', 'H25B' => 'Hawker 800', 'H25C' => 'Hawker 1000', 'HA4T' => 'Hawker 4000',
    'HDJT' => 'HondaJet', 'SF50' => 'Cirrus SF50', 'PC24' => 'Pilatus PC-24', 'P180' => 'Piaggio P.180',
    // Turboprops und Allgemeine Luftfahrt
    'PC12' => 'Pilatus PC-12', 'PC6T' => 'Pilatus Porter', 'PC7' => 'Pilatus PC-7', 'PC9' => 'Pilatus PC-9', 'PC21' => 'Pilatus PC-21',
    'BE9L' => 'King Air 90', 'BE9T' => 'King Air F90', 'BE20' => 'King Air 200', 'B350' => 'King Air 350',
    'BE33' => 'Bonanza 33', 'BE35' => 'Bonanza 35', 'BE36' => 'Bonanza 36', 'BE55' => 'Baron 55', 'BE58' => 'Baron 58', 'BE76' => 'Duchess 76',
    'TBM7' => 'TBM 700', 'TBM8' => 'TBM 850', 'TBM9' => 'TBM 900',
    'C150' => 'Cessna 150', 'C152' => 'Cessna 152', 'C162' => 'Cessna 162', 'C172' => 'Cessna 172', 'C177' => 'Cessna 177',
    'C182' => 'Cessna 182', 'C206' => 'Cessna 206', 'C208' => 'Cessna Caravan', 'C210' => 'Cessna 210',
    'C310' => 'Cessna 310', 'C340' => 'Cessna 340', 'C414' => 'Cessna 414', 'C421' => 'Cessna 421', 'C441' => 'Cessna Conquest',
    'P28A' => 'Piper PA-28', 'P28B' => 'Piper PA-28', 'P28R' => 'Piper Arrow', 'P32R' => 'Piper Saratoga', 'PA32' => 'Piper PA-32',
    'PA34' => 'Piper Seneca', 'PA44' => 'Piper Seminole', 'PA31' => 'Piper Navajo', 'PA46' => 'Piper Malibu', 'P46T' => 'Piper Meridian',
    'PA18' => 'Piper Super Cub', 'J3' => 'Piper J-3 Cub', 'PA24' => 'Piper Comanche',
    'DA40' => 'Diamond DA40', 'DA42' => 'Diamond DA42', 'DA62' => 'Diamond DA62', 'DV20' => 'Diamond DV20',
    'DR40' => 'Robin DR400', 'SR20' => 'Cirrus SR20', 'SR22' => 'Cirrus SR22', 'S22T' => 'Cirrus SR22T',
    'M20P' => 'Mooney M20', 'M20T' => 'Mooney M20', 'TB10' => 'Socata TB-10', 'TB20' => 'Socata TB-20',
    'G115' => 'Grob G115', 'G109' => 'Grob G109', 'EV97' => 'EuroStar EV97',
    // Hubschrauber
    'EC20' => 'EC120', 'EC30' => 'H130', 'EC35' => 'H135', 'EC45' => 'H145', 'EC55' => 'H155', 'EC75' => 'H175', 'EC25' => 'H225',
    'AS32' => 'Super Puma', 'AS50' => 'AS350 Ecureuil', 'AS55' => 'AS355 Ecureuil', 'AS65' => 'AS365 Dauphin', 'BK17' => 'BK 117',
    'A109' => 'AW109', 'A119' => 'AW119', 'A139' => 'AW139', 'A149' => 'AW149', 'A169' => 'AW169', 'A189' => 'AW189',
    'B06' => 'Bell 206', 'B407' => 'Bell 407', 'B412' => 'Bell 412', 'B429' => 'Bell 429', 'B505' => 'Bell 505',
    'R22' => 'Robinson R22', 'R44' => 'Robinson R44', 'R66' => 'Robinson R66',
    'S76' => 'Sikorsky S-76', 'S92' => 'Sikorsky S-92', 'H60' => 'Black Hawk', 'NH90' => 'NH90', 'H47' => 'CH-47 Chinook',
    'MI8' => 'Mil Mi-8', 'TIGR' => 'Tiger',
    // Militaer
    'K35R' => 'KC-135R', 'K35E' => 'KC-135E', 'KC46' => 'KC-46 Pegasus', 'E3TF' => 'E-3 Sentry', 'E3CF' => 'E-3 Sentry',
    'E6' => 'E-6 Mercury', 'P8' => 'P-8 Poseidon', 'R135' => 'RC-135', 'C17' => 'C-17', 'C5M' => 'C-5M Galaxy',
    'C130' => 'C-130 Hercules', 'C30J' => 'C-130J Hercules', 'F16' => 'F-16', 'F15' => 'F-15', 'F18' => 'F/A-18',
    'F35' => 'F-35', 'EUFI' => 'Eurofighter', 'RFAL' => 'Rafale', 'TOR' => 'Tornado', 'A10' => 'A-10', 'B52' => 'B-52',
    'HAWK' => 'Hawk', 'ALPH' => 'Alpha Jet', 'T38' => 'T-38',
    // Was sonst noch fliegt
    'GLID' => 'Glider', 'BALL' => 'Balloon', 'ULAC' => 'Ultralight', 'GYRO' => 'Gyrocopter', 'SHIP' => 'Airship',
];

/**
 * Name fuer das Panel und die Karte. Ohne Eintrag das Kuerzel selbst, ohne
 * Kuerzel ein Fragezeichen. Laengere Namen als $max gibt es in der Tabelle nicht,
 * die Pruefung bleibt fuer den Fall, dass jemand sie ergaenzt.
 */
function aircraft_type_name(string $icao, int $max = 15): string
{
    $code = strtoupper(trim($icao));
    if ($code === '') {
        return '?';
    }
    $name = AIRCRAFT_TYPES[$code] ?? null;
    if ($name === null || strlen($name) > $max) {
        return substr($code, 0, $max);
    }
    return $name;
}
