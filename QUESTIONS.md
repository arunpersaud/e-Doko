# Questions and decisions to check (live play session, 2026-10-31)

Not committed; delete when answered.

1. **Digest users get turn mails immediately.** The digest (user.digest_hours) is
   not ported yet (todo item 4), so play.py mails everybody right away unless
   mail_mode is 'none'. mail_mode 'fewer' (old "emailaddict") gets no "your turn"
   mails, only invitations, cancellations and game over, as in PHP. OK?
2. **Schweinchen "secondaftercall"**: the trick winner is decided with the calls
   made *so far*; the final scoring (rules.replay) uses the final calls. A call
   made after the second fox could in theory make the two differ. Very rare;
   replay_check on live games found no difference. Leave it?
3. **Wedding and poverty are refused at the declaration** with a message ("can
   not be played on this site yet") until they are ported. Both club queens
   without declaring a wedding = silent solo (as in PHP).
4. **Tables**: only *finished* games count towards `max_games` (a solo counts, a
   canceled game does not and is dealt again). Tie at the end: all tied players are
   shown as winners. Seats are drawn by lot at a new table. The new-table form
   defaults to the PHP defaults (second ten wins, no Schweinchen, call on the first
   own card) but low trump = "cancel" (poverty is not playable yet), and "number of
   games" is empty = no end. What default do you want?
5. "Keep playing" is offered after a finished game and after a game canceled for
   nines/low trump (same dealer again, as PHP). Not after "a player does not want
   to play" or a timeout (as in PHP). The same dealer repeats after a solo, but not
   after a silent solo.
6. **Poverty** is ported as in PHP (trump <27 goes to the taker, who gives the same
   number of *any* cards back one click at a time; poor player + taker are Re).
   Differences, on purpose: in a double poverty PHP asks seat 3 first whenever seat 1
   is poor (a bug: seat 3 can be poor too); I ask the first seat that is not poor.
   Three or four poor players: the game is canceled ("nobody wanted the trump").
   A poor player's page just says "waiting" while the others decide.
7. **Reminder/cancel**: a reminder after 7 idle days, one per game and week, to the
   player on turn (PHP: once per player and game, ever). Cancel after 30 idle days.
8. The **German translations** (po/de.po) are not updated for the new texts of live
   play yet; the pages are English for German users until then.
