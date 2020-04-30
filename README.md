# InstantRespawn
Respawn players without death screen

With this plugin you are able to let players respawn instantly when they die and also cancel move for * seconds.

## Features
- ⏱ Configure respawn delay
- 👻 Hide dead players
- 🕶 Make player blind and immobile whilst respawning
- 🌍 Select worlds to respawn in
- 🧹 Resets all effects, fills hunger and extinguish players
- 💡 PlayerDeathEvent and PlayerRespawnEvent still functioning
- 🛑 Block events and damage whilst respawning

## Commands & How to use
### Command
- `/irespawn` - `Opens an UI to modify the settings of InstantRespawn`
### Permission
`irespawn`
### Config
Install the plugin and change the `config.yml`

⚠ By default the plugin will not change the respawn behaviour, you will need to enable it in worlds first! ⚠

You **MAY NOT** change the `worlds` entry in `config.yml` by hand, use the command to avoid mistakes.
## Known Bugs
- `Player::isAlive()` will return true whilst respawning