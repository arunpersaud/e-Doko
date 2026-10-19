"""A software passkey, to test registration and login without a browser."""

import hashlib
import json
import os
import struct

import cbor2
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import ec
from webauthn.helpers import bytes_to_base64url


class FakeAuthenticator:
    def __init__(self, rp_id="localhost", origin="http://localhost:5000"):
        self.rp_id = rp_id
        self.origin = origin
        self.key = ec.generate_private_key(ec.SECP256R1())
        self.credential_id = os.urandom(16)
        self.counter = 0

    def _client_data(self, kind, challenge):
        return json.dumps(
            {"type": kind, "challenge": challenge, "origin": self.origin}
        ).encode()

    def _auth_data(self, flags, attested=b""):
        rp_hash = hashlib.sha256(self.rp_id.encode()).digest()
        return rp_hash + bytes([flags]) + struct.pack(">I", self.counter) + attested

    def register(self, options):
        numbers = self.key.public_key().public_numbers()
        cose = cbor2.dumps(
            {
                1: 2,
                3: -7,
                -1: 1,
                -2: numbers.x.to_bytes(32, "big"),
                -3: numbers.y.to_bytes(32, "big"),
            }
        )
        attested = (
            bytes(16)
            + struct.pack(">H", len(self.credential_id))
            + self.credential_id
            + cose
        )
        auth_data = self._auth_data(0x45, attested)  # user present+verified, attested
        attestation = cbor2.dumps({"fmt": "none", "attStmt": {}, "authData": auth_data})
        cid = bytes_to_base64url(self.credential_id)
        return {
            "id": cid,
            "rawId": cid,
            "type": "public-key",
            "response": {
                "clientDataJSON": bytes_to_base64url(
                    self._client_data("webauthn.create", options["challenge"])
                ),
                "attestationObject": bytes_to_base64url(attestation),
            },
            "clientExtensionResults": {},
        }

    def login(self, options, user_handle=None):
        self.counter += 1
        auth_data = self._auth_data(0x05)  # user present + verified
        client_data = self._client_data("webauthn.get", options["challenge"])
        signature = self.key.sign(
            auth_data + hashlib.sha256(client_data).digest(),
            ec.ECDSA(hashes.SHA256()),
        )
        cid = bytes_to_base64url(self.credential_id)
        response = {
            "authenticatorData": bytes_to_base64url(auth_data),
            "clientDataJSON": bytes_to_base64url(client_data),
            "signature": bytes_to_base64url(signature),
        }
        if user_handle:
            response["userHandle"] = bytes_to_base64url(user_handle)
        return {
            "id": cid,
            "rawId": cid,
            "type": "public-key",
            "response": response,
            "clientExtensionResults": {},
        }
