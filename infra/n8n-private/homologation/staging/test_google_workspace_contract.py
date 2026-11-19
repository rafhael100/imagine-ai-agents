"""Exercise a synthetic operator policy without contacting Google or Tailscale.

The fixture supplies identity claims as if an OIDC library had already verified
the token. A separate fixture models a gateway-verified factor. This contract
does not implement OIDC, verify a real second factor, or proxy network traffic.
"""

from dataclasses import dataclass
import unittest


@dataclass(frozen=True)
class Identity:
    subject: str
    hosted_domain: str | None
    email: str


@dataclass(frozen=True)
class Session:
    subject: str
    expires_at: int


class OperatorPolicy:
    def __init__(self, domain, operators):
        self.domain = domain
        self.operators = set(operators)
        self.sessions = {}

    def authenticate(self, session_id, identity, verified_factor, now):
        if (identity.hosted_domain != self.domain
                or identity.subject not in self.operators
                or not verified_factor):
            return False
        self.sessions[session_id] = Session(identity.subject, now + 1800)
        return True

    def authorize(self, session_id, now, *, trusted_listener):
        session = self.sessions.get(session_id)
        return (trusted_listener and session is not None
                and now < session.expires_at
                and session.subject in self.operators)

    def revoke(self, session_id):
        self.sessions.pop(session_id, None)

    def remove_operator(self, subject):
        self.operators.discard(subject)


class GoogleWorkspaceContractTest(unittest.TestCase):
    def setUp(self):
        self.policy = OperatorPolicy("imagine.invalid", {"operator-1"})
        self.identity = Identity("operator-1", "imagine.invalid", "alice@imagine.invalid")
        self.now = 100

    def login(self):
        self.assertTrue(self.policy.authenticate("session", self.identity, True, self.now))

    def allowed(self, session_id="session", now=100, *, trusted_listener=True):
        return self.policy.authorize(session_id, now, trusted_listener=trusted_listener)

    def test_workspace_identity_and_factor_are_required(self):
        for identity in (
            Identity("operator-1", None, "alice@imagine.invalid"),
            Identity("operator-1", "other.invalid", "alice@imagine.invalid"),
            Identity("unknown", "imagine.invalid", "alice@imagine.invalid"),
        ):
            with self.subTest(identity=identity):
                self.assertFalse(self.policy.authenticate("session", identity, True, self.now))
                self.assertFalse(self.allowed())
        self.assertFalse(self.policy.authenticate("session", self.identity, False, self.now))
        self.assertFalse(self.allowed())
        self.login()
        self.assertTrue(self.allowed())

    def test_missing_session_and_untrusted_listener_are_denied(self):
        self.assertFalse(self.allowed())
        self.login()
        self.assertFalse(self.allowed("arbitrary-client-value"))
        self.assertFalse(self.allowed(trusted_listener=False))

    def test_session_expires_at_absolute_boundary(self):
        self.login()
        self.assertTrue(self.allowed(now=self.now + 1799))
        self.assertFalse(self.allowed(now=self.now + 1800))

    def test_revocation_applies_on_next_authorization(self):
        self.login()
        self.assertTrue(self.allowed())
        self.policy.revoke("session")
        self.assertFalse(self.allowed())

    def test_removing_operator_invalidates_existing_session(self):
        self.login()
        self.assertTrue(self.allowed())
        self.policy.remove_operator(self.identity.subject)
        self.assertFalse(self.allowed())
        self.assertFalse(self.policy.authenticate("new-session", self.identity, True, self.now))


if __name__ == "__main__":
    unittest.main()
