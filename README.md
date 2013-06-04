# Light 'Em Up - Call of Duty: Modern Warfare

Manage Philips Hue lights based on Call of Duty 4: Modern Warfare multiplayer log activity.

## Introduction

This project was originally developed at [Mashery, Inc](http://mashery.com) as a proof of concept for triggering lights in the Philips Hue system after logged events.

We decided to hook up a Philips Hue system to a traffic sign that powered standard 60W bulbs. We  tailed Call of Duty multi-player logs for activity on the server, and sent commands to the lights using (Phue)[http://github.com/sqmk/Phue] for anything interesting that was logged.

When a Call of Duty server started and ended, we would toggle the green light of the traffic sign. Any damage incurred in the game would result in a flicker of the yellow light. Any player deaths would fade out the red light. Light activity was nearly instantaneous once a new message was logged.

## Requirements

- PHP 5.4+
- Call of Duty: Modern Warfare
- Friends to play with you!
