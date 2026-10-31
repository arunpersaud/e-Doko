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
