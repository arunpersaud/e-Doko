"""Amazon SNS messages: check that they really come from AWS.

SNS posts a JSON document and signs it with a certificate that it publishes
at a URL inside the message. So the URL must be an SNS address of AWS (else
anybody could name their own certificate), and the signature must match the
fields that SNS documents for the type of the message.
"""

import re
from base64 import b64decode
from datetime import UTC, datetime
from functools import lru_cache
from urllib.parse import urlsplit
from urllib.request import urlopen

from cryptography import x509
from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import padding

SNS_HOST = re.compile(r"sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?")

# the fields that are signed, in this order (a missing one is left out)
SIGNED = {
    "Notification": (
        "Message",
        "MessageId",
        "Subject",
        "Timestamp",
        "TopicArn",
        "Type",
    ),
    "SubscriptionConfirmation": (
        "Message",
        "MessageId",
        "SubscribeURL",
        "Timestamp",
        "Token",
        "TopicArn",
        "Type",
    ),
}
SIGNED["UnsubscribeConfirmation"] = SIGNED["SubscriptionConfirmation"]

HASHES = {"1": hashes.SHA1, "2": hashes.SHA256}  # noqa: S303 (what SNS version 1 uses)


class Invalid(Exception):
    """The message is not a genuine SNS message."""


def trusted_url(url: object) -> bool:
    """An https address on an SNS host of AWS, without credentials or a port."""
    if not isinstance(url, str):
        return False
    try:
        parts = urlsplit(url)
        return (
            parts.scheme == "https"
            and parts.port is None
            and parts.username is None
            and SNS_HOST.fullmatch(parts.hostname or "") is not None
        )
    except ValueError:
        return False


@lru_cache(maxsize=8)
def certificate(url: str) -> bytes:
    """The PEM certificate at an SNS address (cached: SNS keeps using the same)."""
    with urlopen(url, timeout=10) as response:  # noqa: S310 (checked by trusted_url)
        return response.read(64 * 1024)


def verify(message: dict) -> None:
    """Raise Invalid unless message is a signed, genuine SNS message."""
    fields = SIGNED.get(message.get("Type"))
    if fields is None or message.get("SignatureVersion") not in HASHES:
        raise Invalid("unknown message type or signature version")
    if not all(
        isinstance(message.get(k), str) for k in ("Signature", "SigningCertURL")
    ):
        raise Invalid("no signature")
    if not trusted_url(message["SigningCertURL"]):
        raise Invalid("the certificate is not from SNS")
    text = "".join(
        f"{name}\n{message[name]}\n"
        for name in fields
        if isinstance(message.get(name), str)
    )
    try:
        cert = x509.load_pem_x509_certificate(certificate(message["SigningCertURL"]))
        if cert.not_valid_after_utc < datetime.now(UTC):
            raise Invalid("the certificate has expired")
        cert.public_key().verify(
            b64decode(message["Signature"], validate=True),
            text.encode(),
            padding.PKCS1v15(),
            HASHES[message["SignatureVersion"]](),
        )
    except (InvalidSignature, ValueError, TypeError, AttributeError, OSError) as error:
        raise Invalid("the signature does not match") from error


def confirm(url: str) -> None:
    """Confirm a subscription by opening the address SNS sent (if it is SNS's)."""
    if not trusted_url(url):
        raise Invalid("the confirmation address is not from SNS")
    with urlopen(url, timeout=10):  # noqa: S310
        pass
