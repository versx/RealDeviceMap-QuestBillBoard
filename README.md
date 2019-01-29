# QuestBillBoard  
Billboard to show field research quests with quest reward and geofence city filters.  

# Install  
```  
git clone https://github.com/versx/RealDeviceMap-QuestBillBoard quests (change `quests` to your liking)  
Install Composer (https://getcomposer.org/)  
mkdir geofences (in your cloned `quests` folder i.e. /var/www/site/quests)  
```

# Geofences  
Create or copy your existing geofences to the `geofences` folder. The following is the expected format:   
```
[City Name]  
0,0  
1,1  
2,2  
3,3  
```

# config.php  
Required: Configure the database variables to match what is needed to access your RealDeviceMap Database. Make sure to enter your RealDeviceMap backend url in order to grab the pokemon icons.  
Optionally: You can add your Google Analytics and Google Ad-Sense ids to the config file. You also have a few different table properties to customize to your liking.  

# Thanks  
- Credit to Zyahko and his creditors for the base.  
