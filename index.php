<?php
include './vendor/autoload.php';
include './config.php';
include './pokedex.php';
include './geofence_service.php';

$googleMapsLink = "https://maps.google.com/maps?q=%s,%s";
$appleMapsLink = "https://maps.apple.com/maps?daddr=%s,%s";

$geofence_srvc = new GeofenceService();

$filters = "
<div class='panel panel-default'>
<div class='form-group row'>
  <div class='col-md-4'> 
    <div class='input-group'>
    Search by reward:&nbsp;
    <input type='text' id='search-input' class='form-control input-lg' style='display:initial !important;' onkeyup='filter_quests()' placeholder='Search by name..' title='Type in a name'>
  </div>
</div>
<div class='col-md-4'> 
  <div class='input-group'>
    Search by city:&nbsp;
    <select id='filter-city' class='form-control' style='display:initial !important;' onchange='filter_quests()'>
      <option disabled selected>Select</option>
      <option value='all'>All</option>";
      $count = count($geofence_srvc->geofences);
      for ($i = 0; $i < $count; $i++) {
        $geofence = $geofence_srvc->geofences[$i];
        $filters .= "<option value='".$geofence->name."'>".$geofence->name."</option>";
      }
      $filters .= "
      </select>
    </div>
  </div>
</div>
";

// Establish connection to database
try {
  $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;port=$dbPort", $dbuser, $dbpass);
  // Set the PDO error mode to exception
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
  die("ERROR: Could not connect. " . $e->getMessage());
}
// Query Database and Build Raid Billboard
try {
  $sql = "
SELECT 
    lat, 
    lon,
    quest_type,
    quest_timestamp, 
    quest_target,
    quest_conditions,
	quest_rewards,
	quest_template,
	quest_pokemon_id,
	quest_reward_type,
	quest_item_id,
    name
FROM 
    rdmdb.pokestop
WHERE
    quest_type IS NOT NULL && 
    name IS NOT NULL &&
    enabled=1;
";

  $result = $pdo->query($sql);
  if ($result->rowCount() > 0) {
    echo $filters;
    echo "<table class='table table-".$table_style." ".($table_striped ? 'table-striped' : null)."' border='1' id='quest-table';>";
    echo "<thead class='thead-".$table_header_style."'>";
    echo "<tr>";
        echo "<th>Reward</th>";
        echo "<th>Quest</th>";
        echo "<th>Condition(s)</th>";
        echo "<th>City</th>";
        echo "<th>Pokestop</th>";
    echo "</tr>";
    echo "</thead>";
    while ($row = $result->fetch()) {	
      $geofence = $geofence_srvc->get_geofence($row['lat'], $row['lon']);
      $city = ($geofence == null ? $unknown_value : $geofence->name);
      $map_link = sprintf($googleMapsLink, $row["lat"], $row["lon"]);

      $quest_conditions_object = json_decode($row['quest_conditions']);
      $quest_rewards_object = json_decode($row['quest_rewards']);
      $quest_message = get_quest_message($row['quest_type'], $row['quest_target']);
	  $quest_reward = get_quest_reward($quest_rewards_object);
	  $quest_conditions_message = get_quest_conditions($quest_conditions_object);
	  $quest_icon = get_quest_icon($quest_rewards_object);

      echo "<tr>";
        echo "<td scope='row'><a title='Remove' data-toggle='tooltip' class='delete'>X&nbsp;</a><img src='$quest_icon' height=32 width=32 />&nbsp;" . $quest_reward . "</td>";
        echo "<td>" . $quest_message . "</td>";
        echo "<td>" . $quest_conditions_message . "</td>";
        echo "<td>" . $city . "</td>";
        echo "<td><a href='" . $map_link . "' target='_blank'>" . $row['name'] . "</a></td>";
      echo "</tr>";
    }
    echo "</table>";
		
  // Free result set
  unset($result);
  } else{
    echo "<p>No field research quests available.</p>";
  }
} catch(PDOException $e){
  die("ERROR: Could not able to execute $sql. " . $e->getMessage());
}
// Close connection
unset($pdo);

if ($google_analytics_id != "") {
  echo "
<!-- Google Analytics -->
<script>
  window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;
  ga('create', '" . $google_analytics_id . "', 'auto');
  ga('send', 'pageview');
</script>
<script async src='https://www.google-analytics.com/analytics.js'></script>
<!-- End Google Analytics -->'";
}

if ($google_adsense_id != "") {
  echo "
<script async src='//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js'></script>
<script>
  (adsbygoogle = window.adsbygoogle || []).push({
    google_ad_client: '" . $google_adsense_id . "',
    enable_page_level_ads: true
  });
</script>";
}

function get_quest_message($type, $target) {
  switch ($type) {
    case 22://QuestType.AddFriend:
      $msg = "Add %s Friends";
        break;
    case 12://QuestType.AutoComplete:
      $msg = "Autocomplete";
        break;
    case 18://QuestType.BadgeRank:
      $msg = "Get %s Badge(s)";
        break;
    case 4://QuestType.CatchPokemon:
      $msg = "Catch %s Pokemon";
        break;
    case 21://QuestType.CompleteBattle:
      $msg = "Complete %s Battles";
        break;
    case 7://QuestType.CompleteGymBattle:
      $msg = "Complete %s Gym Battles";
        break;
    case 9://QuestType.CompleteQuest:
      $msg = "Complete %s Quests";
        break;
    case 8://QuestType.CompleteRaidBattle:
      $msg = "Complete %s Raid Battles";
        break;
    case 25://QuestType.EvolveIntoPokemon:
      $msg = "Evolve %s Into Specific Pokemon";
        break;
    case 15://QuestType.EvolvePokemon:
      $msg = "Evolve %s Pokemon";
        break;
    case 11://QuestType.FavoritePokemon:
      $msg = "Favorite %s Pokemon";
        break;
    case 1://QuestType.FirstCatchOfTheDay:
      $msg = "Catch first Pokemon of the day";
        break;
    case 2://QuestType.FirstPokestopOfTheDay:
      $msg = "Spin first pokestop of the day";
        break;
    case 17://QuestType.GetBuddyCandy:
      $msg = "Earn %s candy walking with your buddy";
        break;
    case 6://QuestType.HatchEgg:
      $msg = "Hatch %s Eggs";
        break;
    case 20://QuestType.JoinRaid:
      $msg = "Join %s Raid Battles";
        break;
    case 16://QuestType.LandThrow:
      $msg = "Land %s Throws";
        break;
    case 3://QuestType.MultiPart:
      $msg = "Multi Part Quest";
        break;
    case 19://QuestType.PlayerLevel:
      $msg = "Reach level %s"; ;
        break;
    case 24://QuestType.SendGift:
      $msg = "Send %s Gifts";
        break;
    case 5://QuestType.SpinPokestop:
      $msg = "Spin %s Pokestops";
        break;
    case 23://QuestType.TradePokemon:
      $msg = "Trade %s Pokemon";
        break;
    case 10://QuestType.TransferPokemon:
      $msg = "Transfer %s Pokemon";
        break;
    case 14://QuestType.UpgradePokemon:
      $msg = "Power up %s Pokemon";
        break;
    case 13://QuestType.UseBerryInEncounter:
      $msg = "Use %s Berries on Pokemon";
        break;
    default: //QuestType.Unknown:
      $msg = "Unknown";
        break;
  }
  return sprintf($msg, $target);
}
function get_quest_conditions($conditions) {
  $count = count($conditions);
  $quest_conditions = [];
  for ($i = 0; $i < $count; $i++) {
    $condition = $conditions[$i];
	switch ($condition->type) {
      case 16://BadgeType
        break;
      case 15://CurveBall
        array_push($quest_conditions, "Curve ball");
        break;
      case 4://DailyCaptureBonus
        array_push($quest_conditions, "Daily catch");
        break;
      case 5://DailySpinBonus
        array_push($quest_conditions, "Daily spin");
        break;
      case 20://DaysInARow
        break;
      case 11://Item
        array_push($quest_conditions, "Use item");
        break;
      case 19://NewFriend
        array_push($quest_conditions, "Make new friend");
        break;
      case 17://PlayerLevel
        array_push($quest_conditions, "Reach level");
        break;
      case 2://PokemonCategory //TODO:
        //list.Add(string.Join(", ", condition.Info.PokemonIds?.Select(x => Database.Instance.Pokemon[x].Name).ToList()));
        break;
      case 1://PokemonType //TODO:
        //list.Add(string.Join(", ", condition.Info.PokemonTypeIds?.Select(x => Convert.ToString((PokemonType)x))) + "-type");
        break;
      case 0://QuestContext
        break;
      case 0://RaidLevel //TODO:
        //list.Add("Level " + string.Join(", ", condition.Info.RaidLevels));
        break;
      case 0://SuperEffectiveCharge
        array_push($quest_conditions, "Super effective charge move");
        break;
      case 0://ThrowType
        array_push($quest_conditions, get_throw_name($condition->info->throw_type_id));
        break;
      case 0://ThrowTypeInARow
        array_push($quest_conditions, sprintf("%s in a row", get_throw_name($condition->info->throw_type_id)));
        break;
      case 0://UniquePokestop
        array_push($quest_conditions, "Unique");
        break;
      case 0://WeatherBoost
        array_push($quest_conditions, "Weather boosted");
        break;
      case 0://WinBattleStatus
        array_push($quest_conditions, "Win battle status");
        break;
      case 0://WinGymBattleStatus
        array_push($quest_conditions, "Win gym battle");
        break;
      case 0://WinRaidStatus
        array_push($quest_conditions, "Win raid status");
        break;
    }
  }
  return join(" ", $quest_conditions);
}
function get_quest_reward($rewards) {
  global $pokedex;
  $reward = $rewards[0];
  switch ($reward->type) {
    case 5: //AvatarClothing
      return "Avatar Clothing";
    case 4: //Candy
      return sprintf("%s Rare Candy", $reward->info->amount);
    case 1: //Experience
      return sprintf("%s XP", $reward->info->amount);
    case 2: //Item
      return sprintf("%s %s", $reward->info->amount, get_item($reward->info->item_id));
    case 7: //PokemonEncounter
      return $pokedex[$reward->info->pokemon_id];
    case 6: //Quest
      return "Quest";
    case 3: //Stardust
      return sprintf("%s Stardust", $reward->info->amount);
    default:
      return "Unknown";
  }
}
function get_throw_name($throw_type_id) {
  switch ($throw_type_id) {
    case 13: //CatchCurveThrow
      return "Curve throw";
    case 12: //CatchExcellentThrow
      return "Excellent throw";
    case 9: //CatchFirstThrow
      return "First throw";
    case 11: //CatchGreatThrow
      return "Great throw";
    case 10: //CatchNiceThrow
      return "Nice throw";
    default:
      return $throw_type_id;
  }
}
function get_item($item_id) {
  switch ($item_id) {
    case 1://Poke_Ball
      return "Poke Ball";
    case 2://Great_Ball
      return "Great Ball";
    case 3://Ultra_Ball
      return "Ultra Ball";
    case 4://Master_Ball
      return "Master Ball";
    case 5://Premier_Ball
      return "Premier Ball";
    case 101://Potion
      return "Potion";
    case 102://Super_Potion
      return "Super Potion";
    case 103://Hyper_Potion
      return "Hyper Potion";
    case 104://Max_Potion
      return "Max Potion";
    case 201://Revive
      return "Revive";
    case 202://Max_Revive
      return "Max Revive";
    case 301://Lucky_Egg
      return "Luck Egg";
    case 401://Incense_Ordinary
      return "Incense";
    case 402://Incense_Spicy
      return "Incense Spicy";
    case 403://Incense_Cool
      return "Incense Cool";
    case 404://Incense_Floral
      return "Incense Floral";
    case 501://Troy_Disk
      return "Troy Disk";
    case 602://X_Attack
      return "X Attack";
    case 603://X_Defense
      return "X Defense";
    case 604://X_Miracle
      return "X Miracle";
    case 701://Razz_Berry
      return "Razz Berry";
    case 702://Bluk_Berry
      return "Bluk Berry";
    case 703://Nanab_Berry
      return "Nanab Berry";
    case 704://Wepar_Berry
      return "Wepar Berry";
    case 705://Pinap_Berry
      return "Pinap Berry";
    case 706://Golden_Razz_Berry
      return "Golden Razz Berry";
    case 707://Golden_Nanab_Berry
      return "Golden Nanab Berry";
    case 708://Golden_Pinap_Berry
      return "Golden Pinap Berry";
    case 701://Special_Camera
      return "Special Camera";
    case 901://Incubator_Basic_Unlimited
      return "Incubator (Unlimited)";
    case 902://Incubator_Basic
      return "Incubator";
    case 903://Incubator_Super
      return "Super Incubator";
    case 1001://Pokemon_Storage_Upgrade
      return "Pokemon Storage Upgrade";
    case 1002://Item_Storage_Upgrade
      return "Item Storage Upgrade";
    case 1101://Sun_Stone
      return "Sun Stone";
    case 1102://Kings_Rock
      return "Kings Rock";
    case 1103://Metal_Coat
      return "Metal Coat";
    case 1104://Dragon_Scale
      return "Dragon Scale";
    case 1105://Upgrade
      return "Upgrade";
    case 1201://Move_Reroll_Fast_Attack
      return "Move Reroll Fast Attack";
    case 1202://Move_Reroll_Special_Attack
      return "Move Reroll Special Attack";
    case 1301://Rare_Candy
      return "Rare Candy";
    case 1401://Free_Raid_Ticket
      return "Free Raid Ticket";
    case 1402://Paid_Raid_Ticket
      return "Paid Raid Ticket";
    case 1403://Legendary_Raid_Ticket
      return "Legendary Raid Ticket";
    case 1404://Star_Piece
      return "Star Piece";
    case 1405://Friend_Gift_Box
      return "Friend Gift Box";
    default:
      return "Unknown";
  }
}
function get_quest_icon($rewards) {
  global $backend_url;
  $icon_index = 0;
  $reward = $rewards[0];
  switch ($reward->type) {
    case 5://AvatarClothing
      break;
    case 4://Candy
      $icon_index = 1301;
      break;
    case 1://Experience
      $icon_index = -2;
      break;
    case 2://Item
      $icon_index = $reward->info->item_id;
      break;
    case 7://Pokemon
      return $backend_url . "/static/img/pokemon/" . $reward->info->pokemon_id . ".png";
    case 6://Quest
      break;
    case 3://Stardust
      $icon_index = -1;
      break;
    default: //Unset/Unknown
      break;
  }
  return "./static/images/$icon_index.png";
}
?>
<html>
<link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css' integrity='sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS' crossorigin='anonymous'>
<script type="text/javascript" src='https://code.jquery.com/jquery-3.3.1.slim.min.js' integrity='sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo' crossorigin='anonymous'></script>
<script type="text/javascript" src='https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js' integrity='sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut' crossorigin='anonymous'></script>
<script type="text/javascript" src='https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js' integrity='sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k' crossorigin='anonymous'></script>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>

<script type="text/javascript">
$(document).on("click", ".delete", function(){
	$(this).parents("tr").remove();
	$(".add-new").removeAttr("disabled");
});

function filter_quests() {
  var search_filter = document.getElementById("search-input").value.toUpperCase();
  var city_filter = document.getElementById("filter-city").value.toUpperCase();
  
  console.log("Quest:", search_filter, "City:", city_filter);
  
  if (city_filter.toLowerCase().indexOf("all") === 0 ||
    city_filter.toLowerCase().indexOf("select") === 0) {
    city_filter = "";
    console.log("City filter cleared");
  }
 
  var table = document.getElementById("quest-table");
  var tr = table.getElementsByTagName("tr");
  for (var i = 0; i < tr.length; i++) {
    if (i == 0)
      continue;
  
    var reward_value = table.rows[i].cells[0].innerHTML.toUpperCase();
    var city_value = table.rows[i].cells[3].innerHTML.toUpperCase();
	
    if (reward_value.indexOf(search_filter) > -1 && city_value.indexOf(city_filter) > -1) {
      tr[i].style.display = "";
    } else {
      tr[i].style.display = "none";
    }     
  }
}
</script>
</html>