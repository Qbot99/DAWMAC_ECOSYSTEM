import { useState } from "react";
import type { FormEvent } from "react";
import { CONTACT } from "../config";
import { useLang } from "../i18n";

interface Props {
  topic: string | null;
  onClearTopic: () => void;
}

export default function ContactSection({ topic, onClearTopic }: Props) {
  const { t, lang } = useLang();
  const [status, setStatus] = useState<"idle" | "sending" | "sent" | "error">(
    "idle"
  );

  const submit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const form = e.currentTarget;
    const fd = new FormData(form);
    setStatus("sending");
    try {
      const res = await fetch("/send_form.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: fd.get("name"),
          contact: fd.get("contact"),
          car: fd.get("car"),
          message: fd.get("message"),
          wheel: topic ?? "",
          lang,
          website: fd.get("website"), // honeypot
        }),
      });
      if (!res.ok) throw new Error(String(res.status));
      setStatus("sent");
      form.reset();
    } catch {
      setStatus("error");
    }
  };

  const channels = [
    {
      label: t.chWhats,
      val: CONTACT.whatsappDisplay,
      href: `https://wa.me/${CONTACT.whatsapp}`,
      wa: true,
    },
    {
      label: t.chPhone,
      val: CONTACT.phoneDisplay,
      href: `tel:${CONTACT.phone}`,
    },
    { label: t.chMail, val: CONTACT.email, href: `mailto:${CONTACT.email}` },
    { label: t.chLoc, val: t.chLocVal, href: CONTACT.mapsUrl },
  ];

  return (
    <section className="section contact" id="kontakt">
      <div data-reveal="0">
        <div className="kicker">
          <span className="kicker__line" />
          <span className="kicker__label">{t.conKicker}</span>
        </div>
        <h2 className="contact__title">
          {t.conTitle1} <span>{t.conTitle2}</span>
        </h2>
      </div>

      <div className="contact__grid">
        <div data-reveal="1">
          <p className="contact__sub">{t.conSub}</p>
          <ul className="contact2__spec">
            {t.points.map((p) => (
              <li key={p}>{p}</li>
            ))}
          </ul>
          <div className="contact2__channels">
            {channels.map((ch) => (
              <a
                key={ch.label}
                className={`contact2-ch ${ch.wa ? "contact2-ch--wa" : ""}`}
                href={ch.href}
                target={ch.href.startsWith("http") ? "_blank" : undefined}
                rel="noopener noreferrer"
              >
                <span className="contact2-ch__label">
                  {ch.label}
                  <span className="contact2-ch__arrow">↗</span>
                </span>
                <span className="contact2-ch__val">{ch.val}</span>
              </a>
            ))}
          </div>
        </div>

        <div data-reveal="2">
          <div className="contact2-form">
            <div className="contact2-form__head">
              <span className="contact2-form__title">{t.formTitle}</span>
              <span className="contact2-form__tag">{t.formTag}</span>
            </div>
            {status === "sent" ? (
              <div className="form__sent">
                <div className="form__sent-title">{t.sentT}</div>
                <div className="form__sent-msg">{t.sentM}</div>
              </div>
            ) : (
              <form className="form" onSubmit={submit}>
                {topic && (
                  <div className="form__topic">
                    {t.asking} <strong>{topic}</strong>
                    <button
                      type="button"
                      onClick={onClearTopic}
                      aria-label="Usuń"
                    >
                      ✕
                    </button>
                  </div>
                )}
                <div className="contact2-form__row">
                  <div>
                    <label className="form__label" htmlFor="f-name">
                      {t.fName}
                    </label>
                    <input
                      id="f-name"
                      className="form__input"
                      name="name"
                      required
                      maxLength={100}
                    />
                  </div>
                  <div>
                    <label className="form__label" htmlFor="f-contact">
                      {t.fContact}
                    </label>
                    <input
                      id="f-contact"
                      className="form__input"
                      name="contact"
                      required
                      maxLength={150}
                    />
                  </div>
                </div>
                <div>
                  <label className="form__label" htmlFor="f-car">
                    {t.fCar}
                  </label>
                  <input
                    id="f-car"
                    className="form__input"
                    name="car"
                    placeholder={t.fCarPh}
                    maxLength={150}
                  />
                </div>
                <div>
                  <label className="form__label" htmlFor="f-msg">
                    {t.fMsg}
                  </label>
                  <textarea
                    id="f-msg"
                    className="form__textarea"
                    name="message"
                    placeholder={t.fMsgPh}
                    maxLength={3000}
                  />
                </div>
                {/* honeypot — boty wypełniają, ludzie nie widzą */}
                <input
                  className="form__hp"
                  type="text"
                  name="website"
                  tabIndex={-1}
                  autoComplete="off"
                />
                {status === "error" && (
                  <p className="form__error">{t.sendErr}</p>
                )}
                <button
                  className="btn-primary"
                  type="submit"
                  disabled={status === "sending"}
                >
                  {status === "sending" ? t.sending : `${t.send} →`}
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
