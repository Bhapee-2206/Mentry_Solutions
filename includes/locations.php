<?php
// includes/locations.php - Master Indian States & Districts Directory & Coordinated Selectors

function getIndiaLocations(): array {
    return [
        'Tamil Nadu' => [
            'Ariyalur', 'Chengalpattu', 'Chennai', 'Coimbatore', 'Cuddalore', 'Dharmapuri', 
            'Dindigul', 'Erode', 'Kallakurichi', 'Kanchipuram', 'Kanyakumari', 'Karur', 
            'Krishnagiri', 'Madurai', 'Mayiladuthurai', 'Nagapattinam', 'Namakkal', 'Nilgiris', 
            'Perambalur', 'Pudukkottai', 'Ramanathapuram', 'Ranipet', 'Salem', 'Sivaganga', 
            'Tenkasi', 'Thanjavur', 'Theni', 'Thoothukudi', 'Tiruchirappalli', 'Tirunelveli', 
            'Tirupathur', 'Tiruppur', 'Tiruvallur', 'Tiruvannamalai', 'Tiruvarur', 'Vellore', 
            'Villupuram', 'Virudhunagar'
        ],
        'Karnataka' => [
            'Bagalkote', 'Ballari', 'Belagavi', 'Bengaluru Rural', 'Bengaluru Urban', 'Bidar', 
            'Chamarajanagar', 'Chikkaballapura', 'Chikkamagaluru', 'Chitradurga', 'Dakshina Kannada', 
            'Davanagere', 'Dharwad', 'Gadag', 'Hassan', 'Haveri', 'Kalaburagi', 'Kodagu', 
            'Kolar', 'Koppal', 'Mandya', 'Mysuru', 'Raichur', 'Ramanagara', 'Shivamogga', 
            'Tumakuru', 'Udupi', 'Uttara Kannada', 'Vijayapura', 'Vijayanagara', 'Yadgir'
        ],
        'Andhra Pradesh' => [
            'Alluri Sitharama Raju', 'Anakapalli', 'Ananthapuramu', 'Annamayya', 'Bapatla', 
            'Chittoor', 'Dr. B.R. Ambedkar Konaseema', 'East Godavari', 'Eluru', 'Guntur', 
            'Kakinada', 'Krishna', 'Kurnool', 'Nandyal', 'NTR', 'Palnadu', 'Parvathipuram Manyam', 
            'Prakasam', 'Sri Potti Sriramulu Nellore', 'Sri Sathya Sai', 'Srikakulam', 'Tirupati', 
            'Visakhapatnam', 'Vizianagaram', 'West Godavari', 'YSR Kadapa'
        ],
        'Telangana' => [
            'Adilabad', 'Bhadradri Kothagudem', 'Hanumakonda', 'Hyderabad', 'Jagtial', 'Jangaon', 
            'Jayashankar Bhupalpally', 'Jogulamba Gadwal', 'Kamareddy', 'Karimnagar', 'Khammam', 
            'Kumuram Bheem Asifabad', 'Mahabubabad', 'Mahabubnagar', 'Mancherial', 'Medak', 
            'Medchal-Malkajgiri', 'Mulugu', 'Nagarkurnool', 'Nalgonda', 'Narayanpet', 'Nirmal', 
            'Nizamabad', 'Peddapalli', 'Rajanna Sircilla', 'Ranga Reddy', 'Sangareddy', 'Siddipet', 
            'Suryapet', 'Vikarabad', 'Wanaparthy', 'Warangal', 'Yadadri Bhuvanagiri'
        ],
        'Kerala' => [
            'Alappuzha', 'Ernakulam', 'Idukki', 'Kannur', 'Kasaragod', 'Kollam', 'Kottayam', 
            'Kozhikode', 'Malappuram', 'Palakkad', 'Pathanamthitta', 'Thiruvananthapuram', 
            'Thrissur', 'Wayanad'
        ],
        'Maharashtra' => [
            'Ahmednagar', 'Akola', 'Amravati', 'Beed', 'Bhandara', 'Buldhana', 'Chandrapur', 
            'Chhatrapati Sambhajinagar', 'Dharashiv', 'Dhule', 'Gadchiroli', 'Gondia', 'Hingoli', 
            'Jalgaon', 'Jalna', 'Kolhapur', 'Latur', 'Mumbai City', 'Mumbai Suburban', 'Nagpur', 
            'Nanded', 'Nandurbar', 'Nashik', 'Palghar', 'Parbhani', 'Pune', 'Raigad', 'Ratnagiri', 
            'Sangli', 'Satara', 'Sindhudurg', 'Solapur', 'Thane', 'Wardha', 'Washim', 'Yavatmal'
        ],
        'Delhi' => [
            'Central Delhi', 'East Delhi', 'New Delhi', 'North Delhi', 'North East Delhi', 
            'North West Delhi', 'Shahdara', 'South Delhi', 'South East Delhi', 'South West Delhi', 'West Delhi'
        ],
        'Gujarat' => [
            'Ahmedabad', 'Amreli', 'Anand', 'Aravalli', 'Banaskantha', 'Bharuch', 'Bhavnagar', 
            'Botad', 'Chhota Udaipur', 'Dahod', 'Dang', 'Devbhumi Dwarka', 'Gandhinagar', 
            'Gir Somnath', 'Jamnagar', 'Junagadh', 'Kheda', 'Kutch', 'Mahisagar', 'Mehsana', 
            'Morbi', 'Narmada', 'Navsari', 'Panchmahal', 'Patan', 'Porbandar', 'Rajkot', 
            'Sabarkantha', 'Surat', 'Surendranagar', 'Tapi', 'Vadodara', 'Valsad'
        ],
        'Haryana' => [
            'Ambala', 'Bhiwani', 'Charkhi Dadri', 'Faridabad', 'Fatehabad', 'Gurugram', 
            'Hisar', 'Jhajjar', 'Jind', 'Kaithal', 'Karnal', 'Kurukshetra', 'Mahendragarh', 
            'Nuh', 'Palwal', 'Panchkula', 'Panipat', 'Rewari', 'Rohtak', 'Sirsa', 'Sonipat', 'Yamunanagar'
        ],
        'Uttar Pradesh' => [
            'Agra', 'Aligarh', 'Ambedkar Nagar', 'Amethi', 'Amroha', 'Auraiya', 'Ayodhya', 
            'Azamgarh', 'Baghpat', 'Bahraich', 'Ballia', 'Balrampur', 'Banda', 'Barabanki', 
            'Bareilly', 'Basti', 'Bhadohi', 'Bijnor', 'Budaun', 'Bulandshahr', 'Chandauli', 
            'Chitrakoot', 'Deoria', 'Etah', 'Etawah', 'Farrukhabad', 'Fatehpur', 'Firozabad', 
            'Gautam Buddha Nagar', 'Ghaziabad', 'Ghazipur', 'Gonda', 'Gorakhpur', 'Hamirpur', 
            'Hapur', 'Hardoi', 'Hathras', 'Jalaun', 'Jaunpur', 'Jhansi', 'Kannauj', 'Kanpur Dehat', 
            'Kanpur Nagar', 'Kasganj', 'Kaushambi', 'Kheri', 'Kushinagar', 'Lalitpur', 'Lucknow', 
            'Maharajganj', 'Mahoba', 'Mainpuri', 'Mathura', 'Mau', 'Meerut', 'Mirzapur', 
            'Moradabad', 'Muzaffarnagar', 'Pilibhit', 'Pratapgarh', 'Prayagraj', 'Raebareli', 
            'Rampur', 'Saharanpur', 'Sambhal', 'Sant Kabir Nagar', 'Shahjahanpur', 'Shamli', 
            'Shravasti', 'Siddharthnagar', 'Sitapur', 'Sonbhadra', 'Sultanpur', 'Unnao', 'Varanasi'
        ],
        'West Bengal' => [
            'Alipurduar', 'Bankura', 'Birbhum', 'Cooch Behar', 'Dakshin Dinajpur', 'Darjeeling', 
            'Hooghly', 'Howrah', 'Jalpaiguri', 'Jhargram', 'Kalimpong', 'Kolkata', 'Malda', 
            'Murshidabad', 'Nadia', 'North 24 Parganas', 'Paschim Bardhaman', 'Paschim Medinipur', 
            'Purba Bardhaman', 'Purba Medinipur', 'Purulia', 'South 24 Parganas', 'Uttar Dinajpur'
        ],
        'Rajasthan' => [
            'Ajmer', 'Alwar', 'Banswara', 'Baran', 'Barmer', 'Bharatpur', 'Bhilwara', 'Bikaner', 
            'Bundi', 'Chittorgarh', 'Churu', 'Dausa', 'Dholpur', 'Dungarpur', 'Hanumangarh', 
            'Jaipur', 'Jaisalmer', 'Jalore', 'Jhalawar', 'Jhunjhunu', 'Jodhpur', 'Karauli', 
            'Kota', 'Nagaur', 'Pali', 'Pratapgarh', 'Rajsamand', 'Sawai Madhopur', 'Sikar', 
            'Sirohi', 'Sri Ganganagar', 'Tonk', 'Udaipur'
        ],
        'Madhya Pradesh' => [
            'Agar Malwa', 'Alirajpur', 'Anuppur', 'Ashoknagar', 'Balaghat', 'Barwani', 'Betul', 
            'Bhind', 'Bhopal', 'Burhanpur', 'Chhatarpur', 'Chhindwara', 'Damoh', 'Datia', 'Dewas', 
            'Dhar', 'Dindori', 'Guna', 'Gwalior', 'Harda', 'Hoshangabad', 'Indore', 'Jabalpur', 
            'Jhabua', 'Katni', 'Khandwa', 'Khargone', 'Mandla', 'Mandsaur', 'Morena', 'Narsinghpur', 
            'Neemuch', 'Niwari', 'Panna', 'Raisen', 'Rajgarh', 'Ratlam', 'Rewa', 'Sagar', 'Satna', 
            'Sehore', 'Seoni', 'Shahdol', 'Shajapur', 'Sheopur', 'Shivpuri', 'Sidhi', 'Singrauli', 
            'Tikamgarh', 'Ujjain', 'Umaria', 'Vidisha'
        ],
        'Punjab' => [
            'Amritsar', 'Barnala', 'Bathinda', 'Faridkot', 'Fatehgarh Sahib', 'Fazilka', 
            'Ferozepur', 'Gurdaspur', 'Hoshiarpur', 'Jalandhar', 'Kapurthala', 'Ludhiana', 
            'Malerkotla', 'Mansa', 'Moga', 'Muktsar', 'Pathankot', 'Patiala', 'Rupnagar', 
            'SAS Nagar', 'Sangrur', 'SBS Nagar', 'Tarn Taran'
        ],
        'Odisha' => [
            'Angul', 'Balangir', 'Balasore', 'Bargarh', 'Bhadrak', 'Boudh', 'Cuttack', 
            'Deogarh', 'Dhenkanal', 'Gajapati', 'Ganjam', 'Jagatsinghpur', 'Jajpur', 'Jharsuguda', 
            'Kalahandi', 'Kandhamal', 'Kendrapara', 'Kendujhar', 'Khordha', 'Koraput', 
            'Malkangiri', 'Mayurbhanj', 'Nabarangpur', 'Nayagarh', 'Nuapada', 'Puri', 'Rayagada', 
            'Sambalpur', 'Subarnapur', 'Sundergarh'
        ],
        'Bihar' => [
            'Araria', 'Arwal', 'Aurangabad', 'Banka', 'Begusarai', 'Bhagalpur', 'Bhojpur', 
            'Buxar', 'Darbhanga', 'East Champaran', 'Gaya', 'Gopalganj', 'Jamui', 'Jehanabad', 
            'Kaimur', 'Katihar', 'Khagaria', 'Kishanganj', 'Lakhisarai', 'Madhepura', 'Madhubani', 
            'Munger', 'Muzaffarpur', 'Nalanda', 'Nawada', 'Patna', 'Purnia', 'Rohtas', 'Saharsa', 
            'Samastipur', 'Saran', 'Sheikhpura', 'Sheohar', 'Sitamarhi', 'Siwan', 'Supaul', 
            'Vaishali', 'West Champaran'
        ],
        'Assam' => [
            'Baksa', 'Barpeta', 'Biswanath', 'Bongaigaon', 'Cachar', 'Charaideo', 'Chirang', 
            'Darrang', 'Dhemaji', 'Dhubri', 'Dibrugarh', 'Dima Hasao', 'Goalpara', 'Golaghat', 
            'Hailakandi', 'Hojai', 'Jorhat', 'Kamrup', 'Kamrup Metropolitan', 'Karbi Anglong', 
            'Karimganj', 'Kokrajhar', 'Lakhimpur', 'Majuli', 'Morigaon', 'Nagaon', 'Nalbari', 
            'Sivasagar', 'Sonitpur', 'South Salmara-Mankachar', 'Tinsukia', 'Udalguri', 'West Karbi Anglong'
        ],
        'Jharkhand' => [
            'Bokaro', 'Chatra', 'Deoghar', 'Dhanbad', 'Dumka', 'East Singhbhum', 'Garhwa', 
            'Giridih', 'Godda', 'Gumla', 'Hazaribagh', 'Jamtara', 'Khunti', 'Koderma', 
            'Latehar', 'Lohardaga', 'Pakur', 'Palamu', 'Ramgarh', 'Ranchi', 'Sahibganj', 
            'Saraikela Kharsawan', 'Simdega', 'West Singhbhum'
        ],
        'Chhattisgarh' => [
            'Balod', 'Baloda Bazar', 'Balrampur', 'Bastar', 'Bemetara', 'Bijapur', 'Bilaspur', 
            'Dantewada', 'Dhamtari', 'Durg', 'Gariaband', 'Gaurela-Pendra-Marwahi', 'Janjgir-Champa', 
            'Jashpur', 'Kabirdham', 'Kanker', 'Kondagaon', 'Korba', 'Koriya', 'Mahasamund', 
            'Mungeli', 'Narayanpur', 'Raigarh', 'Raipur', 'Rajnandgaon', 'Sukma', 'Surajpur', 'Surguja'
        ],
        'Uttarakhand' => [
            'Almora', 'Bageshwar', 'Chamoli', 'Champawat', 'Dehradun', 'Haridwar', 'Nainital', 
            'Pauri Garhwal', 'Pithoragarh', 'Rudraprayag', 'Tehri Garhwal', 'Udham Singh Nagar', 'Uttarkashi'
        ],
        'Himachal Pradesh' => [
            'Bilaspur', 'Chamba', 'Hamirpur', 'Kangra', 'Kinnaur', 'Kullu', 'Lahaul and Spiti', 
            'Mandi', 'Shimla', 'Sirmaur', 'Solan', 'Una'
        ],
        'Goa' => [
            'North Goa', 'South Goa'
        ],
        'Puducherry' => [
            'Karaikal', 'Mahe', 'Puducherry', 'Yanam'
        ],
        'Jammu and Kashmir' => [
            'Anantnag', 'Bandipora', 'Baramulla', 'Budgam', 'Doda', 'Ganderbal', 'Jammu', 
            'Kathua', 'Kishtwar', 'Kulgam', 'Kupwara', 'Poonch', 'Pulwama', 'Rajouri', 
            'Ramban', 'Reasi', 'Samba', 'Shopian', 'Srinagar', 'Udhampur'
        ],
        'Chandigarh' => [
            'Chandigarh'
        ],
        'Tripura' => [
            'Dhalai', 'Gomati', 'Khowai', 'North Tripura', 'Sepahijala', 'South Tripura', 'Unakoti', 'West Tripura'
        ],
        'Meghalaya' => [
            'East Garo Hills', 'East Jaintia Hills', 'East Khasi Hills', 'North Garo Hills', 
            'Ri Bhoi', 'South Garo Hills', 'South West Garo Hills', 'South West Khasi Hills', 
            'West Garo Hills', 'West Jaintia Hills', 'West Khasi Hills'
        ],
        'Manipur' => [
            'Bishnupur', 'Chandel', 'Churachandpur', 'Imphal East', 'Imphal West', 'Jiribam', 
            'Kakching', 'Kamjong', 'Kangpokpi', 'Noney', 'Pherzawl', 'Senapati', 'Tamenglong', 
            'Tengnoupal', 'Thoubal', 'Ukhrul'
        ],
        'Nagaland' => [
            'Chumoukedima', 'Dimapur', 'Kiphire', 'Kohima', 'Longleng', 'Mokokchung', 'Mon', 
            'Niuland', 'Noklak', 'Peren', 'Phek', 'Shamator', 'Tseminyu', 'Tuensang', 'Wokha', 'Zunheboto'
        ],
        'Arunachal Pradesh' => [
            'Anjaw', 'Changlang', 'Dibang Valley', 'East Kameng', 'East Siang', 'Kamle', 
            'Kra Daadi', 'Kurung Kumey', 'Lepa Rada', 'Lohit', 'Longding', 'Lower Dibang Valley', 
            'Lower Siang', 'Lower Subansiri', 'Namsai', 'Pakke Kessang', 'Papum Pare', 'Shi Yomi', 
            'Siang', 'Tawang', 'Tirap', 'Upper Siang', 'Upper Subansiri', 'West Kameng', 'West Siang'
        ],
        'Mizoram' => [
            'Aizawl', 'Champhai', 'Hnahthial', 'Khawzawl', 'Kolasib', 'Lawngtlai', 'Lunglei', 
            'Mamit', 'Saiha', 'Saitual', 'Serchhip'
        ],
        'Sikkim' => [
            'Gangtok', 'Gyalshing', 'Mangan', 'Namchi', 'Pakyong', 'Soreng'
        ],
        'Ladakh' => [
            'Kargil', 'Leh'
        ],
        'Andaman and Nicobar Islands' => [
            'Nicobar', 'North and Middle Andaman', 'South Andaman'
        ],
        'Dadra and Nagar Haveli and Daman and Diu' => [
            'Daman', 'Diu', 'Dadra and Nagar Haveli'
        ],
        'Lakshadweep' => [
            'Lakshadweep'
        ]
    ];
}

/**
 * Identify the proper Indian state for a given district name (case-insensitive & fuzzy match).
 * Handles user errors like "Villupuram, Karnataka" -> auto-corrects State to "Tamil Nadu".
 */
function findStateForDistrict(?string $districtName): ?string {
    if (empty($districtName)) return null;
    $locations = getIndiaLocations();
    $target = strtolower(trim($districtName));
    
    // Direct matches & aliases
    $aliases = [
        'bangalore' => 'Bengaluru Urban',
        'bengaluru' => 'Bengaluru Urban',
        'trichy' => 'Tiruchirappalli',
        'tiruchi' => 'Tiruchirappalli',
        'tuticorin' => 'Thoothukudi',
        'cochin' => 'Ernakulam',
        'kochi' => 'Ernakulam',
        'bombay' => 'Mumbai City',
        'mumbai' => 'Mumbai City',
        'calcutta' => 'Kolkata',
        'madras' => 'Chennai',
        'gurgaon' => 'Gurugram',
        'noida' => 'Gautam Buddha Nagar',
        'pondicherry' => 'Puducherry',
        'hubli' => 'Dharwad',
        'mangaluru' => 'Dakshina Kannada',
        'mangalore' => 'Dakshina Kannada',
        'mysore' => 'Mysuru',
        'baroda' => 'Vadodara',
        'viluppuram' => 'Villupuram',
        'villupuram' => 'Villupuram'
    ];

    if (isset($aliases[$target])) {
        $target = strtolower($aliases[$target]);
    }

    foreach ($locations as $state => $districts) {
        foreach ($districts as $d) {
            if (strtolower($d) === $target || strpos(strtolower($d), $target) !== false || strpos($target, strtolower($d)) !== false) {
                return $state;
            }
        }
    }
    return null;
}

/**
 * Standardizes City and State inputs.
 * If user or database had Villupuram with Karnataka, this guarantees it aligns to Tamil Nadu.
 */
function normalizeIndiaLocation(?string $city, ?string $state): array {
    $city = trim($city ?? '');
    $state = trim($state ?? '');

    $matchedState = findStateForDistrict($city);
    if ($matchedState && (empty($state) || $matchedState !== $state)) {
        // Correct mismatched state
        $state = $matchedState;
    }

    if (empty($state)) {
        $state = 'Tamil Nadu';
    }

    return [$city, $state];
}

/**
 * Renders coordinated HTML <select> elements for State and District with instant JavaScript synchronization.
 */
function renderStateDistrictSelectors(
    string $stateFieldName = 'state',
    string $cityFieldName = 'city',
    string $selectedState = 'Tamil Nadu',
    string $selectedCity = '',
    bool $required = true,
    string $stateLabel = 'State *',
    string $cityLabel = 'District / City *',
    string $themeRing = 'focus:ring-indigo-500/20'
): string {
    $locations = getIndiaLocations();
    
    // Auto-align mismatched state and district if given
    if (!empty($selectedCity)) {
        $expectedState = findStateForDistrict($selectedCity);
        if ($expectedState) {
            $selectedState = $expectedState;
        }
    }

    if (empty($selectedState) || !isset($locations[$selectedState])) {
        $selectedState = 'Tamil Nadu';
    }

    $uniqueId = substr(md5($stateFieldName . '_' . $cityFieldName . '_' . uniqid()), 0, 8);
    $stateSelectId = "state_select_{$uniqueId}";
    $citySelectId = "city_select_{$uniqueId}";

    $reqAttr = $required ? 'required' : '';

    $html = '<div class="grid sm:grid-cols-2 gap-4 w-full">';
    
    // 1. State Dropdown
    $html .= '<div>';
    $html .= '<label class="block text-xs font-bold text-slate-700 uppercase mb-1">' . htmlspecialchars($stateLabel) . '</label>';
    $html .= '<select name="' . htmlspecialchars($stateFieldName) . '" id="' . $stateSelectId . '" ' . $reqAttr . ' class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white focus:ring-2 ' . $themeRing . ' font-medium text-slate-800">';
    $html .= '<option value="">-- Select State --</option>';
    
    foreach ($locations as $st => $dists) {
        $sel = ($st === $selectedState) ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($st) . '" ' . $sel . '>' . htmlspecialchars($st) . '</option>';
    }
    $html .= '</select>';
    $html .= '</div>';

    // 2. District / City Dropdown
    $currentDistricts = $locations[$selectedState] ?? [];
    $html .= '<div>';
    $html .= '<label class="block text-xs font-bold text-slate-700 uppercase mb-1">' . htmlspecialchars($cityLabel) . '</label>';
    $html .= '<select name="' . htmlspecialchars($cityFieldName) . '" id="' . $citySelectId . '" ' . $reqAttr . ' class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white focus:ring-2 ' . $themeRing . ' font-medium text-slate-800">';
    $html .= '<option value="">-- Select District / City --</option>';
    
    $selectedMatched = false;
    foreach ($currentDistricts as $dst) {
        $isMatch = (strcasecmp($dst, $selectedCity) === 0);
        if ($isMatch) $selectedMatched = true;
        $sel = $isMatch ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($dst) . '" ' . $sel . '>' . htmlspecialchars($dst) . '</option>';
    }

    if (!empty($selectedCity) && !$selectedMatched) {
        $html .= '<option value="' . htmlspecialchars($selectedCity) . '" selected>' . htmlspecialchars($selectedCity) . '</option>';
    }

    $html .= '</select>';
    $html .= '</div>';

    $html .= '</div>';

    // 3. Embedded synchronization script
    $jsonLocations = json_encode($locations);
    $escapedSelectedCity = json_encode($selectedCity);

    $html .= "
    <script>
    (function() {
        const locationsMap = {$jsonLocations};
        const stateSelect = document.getElementById('{$stateSelectId}');
        const citySelect = document.getElementById('{$citySelectId}');
        let initialCity = {$escapedSelectedCity};

        if (stateSelect && citySelect) {
            function updateDistricts(selectedState, preselectCity) {
                const districts = locationsMap[selectedState] || [];
                citySelect.innerHTML = '<option value=\"\">-- Select District / City --</option>';
                
                let foundMatch = false;
                districts.forEach(function(dist) {
                    const opt = document.createElement('option');
                    opt.value = dist;
                    opt.textContent = dist;
                    if (preselectCity && dist.toLowerCase() === preselectCity.toLowerCase()) {
                        opt.selected = true;
                        foundMatch = true;
                    }
                    citySelect.appendChild(opt);
                });

                if (preselectCity && !foundMatch && districts.length > 0) {
                    const customOpt = document.createElement('option');
                    customOpt.value = preselectCity;
                    customOpt.textContent = preselectCity;
                    customOpt.selected = true;
                    citySelect.appendChild(customOpt);
                }
            }

            stateSelect.addEventListener('change', function() {
                updateDistricts(this.value, null);
            });
        }
    })();
    </script>
    ";

    return $html;
}
