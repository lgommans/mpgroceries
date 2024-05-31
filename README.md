# Multiplayer Groceries

Just putting this out here in case it is of interest to anyone. You can try
the application here: https://lucgommans.nl/p/grocerylist

![application screenshot](https://github.com/lgommans/mpgroceries/assets/5626710/784c351f-5b8d-434f-9d21-2541b780af07)

In the screenshot you see the main screen of the application, with a few
buttons on top, items below (in the most-frequently-used order), and then a
list of what is currently on the grocery list. The "1" at the beginning of
lines can be an amount, like 400g; for most items I don't use it because we
buy a standard package size and the number just indicates how many packages.

---

The code is very ugly indeed. I'm really not happy
with some choices (like treating items as strings instead of having a UID for
them, which in some cases I work around by piggy-backing off the popularitems
table).

When (if) I find more time, I'll put more info in the readme, like intended
use and how to setup.

