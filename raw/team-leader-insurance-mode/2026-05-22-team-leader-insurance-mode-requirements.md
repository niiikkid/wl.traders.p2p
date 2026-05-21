# Team Leader Insurance Mode Requirements

> Source: User conversation in Cursor
> Collected: 2026-05-22
> Published: Unknown

Business feature: add a second operating mode for the existing Team Leader functionality.

## Current Mode

The existing Team Leader mode stays as the default:

- each trader maintains their own insurance reserve deposit;
- Team Leader does not maintain an insurance reserve deposit for connected traders;
- trader reserve logic continues to use the trader wallet.

## New Mode

The second mode changes the insurance reserve source:

- Team Leader maintains an insurance reserve deposit;
- connected traders do not maintain their own insurance reserve deposit;
- connected traders use the Team Leader `reserve_balance`;
- connected traders' own `reserve_balance` is not used;
- Team Leader `teamleader_balance` is not used for this reserve logic.

## Admin Settings

When creating and editing a Team Leader, admin must be able to:

- select the Team Leader operating mode;
- set the maximum number of trader accounts that may be connected to this Team Leader;
- set the Team Leader required insurance reserve amount;
- set the minimum Team Leader reserve balance threshold at which trades stop being issued to connected traders.

The first operating mode must be selected by default for all new and existing Team Leaders.

The admin UI must clearly explain:

- how the second operating mode works;
- what amount the Team Leader must deposit into reserve;
- at what reserve balance trades stop being issued to connected traders.

## Restrictions On Changing Mode

If a Team Leader already has connected traders, the operating mode cannot be changed.

Switching from mode 1 to mode 2 is allowed only when the Team Leader has no connected traders.

Switching from mode 2 to mode 1 is allowed only when:

- the Team Leader has no connected traders;
- Team Leader `reserve_balance` is zero.

Team Leader `teamleader_balance` does not block switching from mode 2 to mode 1.

## Connecting Traders

Trader accounts are still created by admin, as now.

Team Leader does not create trader accounts.

Admin connects trader accounts to Team Leader through the existing logic. Team Leader assigned to a trader remains permanent and cannot be changed.

When the target Team Leader uses mode 2:

- the system must count all traders with this `team_leader_id`, including active and blocked traders;
- the system must know how many trader accounts remain available under the configured limit;
- connecting another trader past the limit is forbidden;
- admin sees an error that the trader limit is exhausted;
- connecting a trader is forbidden if any trader wallet balance is non-zero.

The non-zero balance restriction includes all trader balances, not only `reserve_balance`.

## Team Leader Reserve Balance Logic

Mode 2 uses Team Leader wallet `reserve_balance` as the shared insurance reserve for connected traders.

Admin configures:

- required Team Leader reserve amount;
- minimum Team Leader reserve balance threshold for issuing trades.

Example:

- Team Leader must deposit insurance reserve: 3000.
- Trades stop being issued to connected traders when reserve balance reaches 1000.

If Team Leader `reserve_balance` is equal to or below the threshold, the system must stop issuing trades to connected traders.

Team Leader must see these conditions explicitly.

Team Leader can top up only their `reserve_balance` for this feature. The top-up flow should be like trader balance top-up, but without overflow into `teamleader_balance`.

Team Leader cannot withdraw reserve balance directly through a self-service Team Leader flow. Reserve withdrawal is done only by admin through the admin panel after a direct request from the Team Leader. The Team Leader UI must display this information.

## Team Leader Income Balance Logic

Team Leader income always goes to `teamleader_balance`.

This is strict:

- Team Leader income must not fill `reserve_balance`;
- Team Leader reserve top-up must not fill `teamleader_balance`;
- Team Leader `teamleader_balance` must remain untouched by connected trader reserve spending;
- connected trader insurance logic must use only Team Leader `reserve_balance` as the shared reserve source.

## Trader Balance Logic Under Mode 2

When a trader is connected to a Team Leader using mode 2:

- trader insurance deposit is disabled;
- trader `reserve_balance` is not used;
- trader UI shows that the trader works via Team Leader insurance reserve;
- trader top-ups go directly to trader `trust_balance`, bypassing trader reserve;
- funds are no longer credited to trader reserve;
- admin cannot configure trader reserve for this trader;
- trader reserve settings are hidden in admin UI and rejected/ignored by backend.

Trade debit logic under mode 2:

- first debit trader `trust_balance`;
- if trader `trust_balance` is insufficient, debit the remaining amount from Team Leader `reserve_balance`;
- do not debit trader `reserve_balance`;
- do not debit Team Leader `teamleader_balance`.

The debit should be recorded as ordinary wallet transactions; no separate custom transaction type is required unless implementation discovers a technical need.

## Compatibility Requirement

The new logic must activate only when both conditions are true:

- Team Leader uses mode 2;
- trader is connected to that Team Leader.

All other users and all Team Leaders in mode 1 must continue using current behavior.
