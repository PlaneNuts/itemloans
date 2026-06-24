# ItemLoans GLPI plugin

This plugin was created out of the need for my team to be able to quickly loan and return large numbers of devices. This is my first attempt at a plugin so there may be some bugs. This was made with HEAVY use of Gemeni to help me create this. A lot of witing of code from the AI, and a lot of degugging on my part to fix mistakes. I envy the work you "real devs" are able to do. 

## Features
* A table showing a history of all loans (closed and active) with the ability to use GLPIs powerful native search to find histories of loans by user or device
* Loaning and returning process allows you to quickly scan items into a temporary cart using the items Serial Number, Inventory Number, or Immobilization number
  * Conflicts in IDs give the option to select the correct item.
  * Loaning is limited to the current entitity or child entities
* When loaning items you can quickly set a new location and status for all of the items being loaned (leaving them blank will make no changes to the items)
* It's also possible to send optional notifications to users to ask them to confirm reception of their loans, and return reminders for loans that have a limited duration 
* 'Computer', 'Monitor', 'Phone', 'NetworkEquipment', 'Peripheral', 'Software', 'Printer' item types can be loaned as well as items created with the Generic Objects plugin
* Permissions can be granted to profiles allowing the user to only view, create loans, or return items
* End users with Self Service have the ability to see items currently loaned to them

<img width="907" height="374" alt="image" src="https://github.com/user-attachments/assets/2c490aae-bf91-4183-b1a5-d60346e30a2c" />

<img width="1451" height="742" alt="image" src="https://github.com/user-attachments/assets/56b3027a-76a8-4b22-a0d2-6554a939beca" />



## How to Install
```
cd /my/glpi/deployment/main/directory/plugins
git clone https://github.com/PlaneNuts/itemloans.git
```

* Once installed set permissions in the desired user profiles for the plugin
  <img width="1524" height="771" alt="image" src="https://github.com/user-attachments/assets/05d8ae37-2422-4704-aef9-d4bf6845c21e" />


## Contributing

* Open a ticket for each bug/feature so it can be discussed
* Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
* Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
* Work on a new branch on your own fork
* Open a PR that will be reviewed by a developer
