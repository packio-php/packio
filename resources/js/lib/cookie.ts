const cookieMaxAgeSeconds = 60 * 60 * 24 * 365;

export function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const encodedName = `${encodeURIComponent(name)}=`;
  const cookies = document.cookie ? document.cookie.split("; ") : [];
  const match = cookies.find((cookie) => cookie.startsWith(encodedName));

  if (!match) {
    return null;
  }

  try {
    return decodeURIComponent(match.slice(encodedName.length));
  } catch {
    return null;
  }
}

export function writeCookie(name: string, value: string): void {
  if (typeof document === "undefined") {
    return;
  }

  const secure =
    typeof window !== "undefined" && window.location.protocol === "https:"
      ? "Secure"
      : "";

  document.cookie = [
    `${encodeURIComponent(name)}=${encodeURIComponent(value)}`,
    `Max-Age=${cookieMaxAgeSeconds}`,
    "Path=/",
    "SameSite=Lax",
    secure,
  ]
    .filter(Boolean)
    .join("; ");
}
