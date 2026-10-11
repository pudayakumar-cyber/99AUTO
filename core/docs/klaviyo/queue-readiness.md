# Queue readiness validation

This follow-up targets `fix/klaviyo-email-readiness` (PR #201) until that
branch is merged. It does not depend on coupon usage accounting. If #201 is
squash-merged, reconcile this branch with main before changing the PR base.

Previously `klaviyo:validate` accepted any nonempty queue connection name other
than `sync`. A typo, null driver or custom-named synchronous connection could
therefore pass while discarding jobs or not providing asynchronous delivery.

The command now checks the actual driver of the configured connection. It accepts
the supported asynchronous database, Redis, SQS and Beanstalkd configurations.
Unknown/custom drivers require separate verification and do not pass this check.
For database queues it also reads the configured queue database and verifies the
configured jobs table and required columns. Connection failures return FAIL
without printing database connection exception details.

The check does not dispatch jobs, send messages, change queue configuration or
enable Klaviyo. It does not prove a Redis/SQS/Beanstalkd connection works or that
any worker is running. A PASS means configuration/schema validation only.

Staging with queues disabled is expected to fail the delivery prerequisite.
Do not enable real outbound delivery merely to obtain a green report. Before
controlled testing, configure an isolated test queue and worker, and restrict
Klaviyo activity to authorized test profiles/flows.

No migration is needed for this follow-up. Reverting the commit restores the
previous validator without affecting storefront or worker behavior.
