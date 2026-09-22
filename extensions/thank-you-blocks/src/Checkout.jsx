import '@shopify/ui-extensions/preact';
import { render } from 'preact';
import { useEffect, useState } from 'preact/hooks';

const APP_URL = 'https://vantora-production.up.railway.app';

/**
 * F-34 Thank You Page Offers, F-35 Post-purchase Survey, F-37 Referral
 * (Thank You side). One network call on mount fetches everything this
 * page needs; each block only renders if the backend actually has content
 * for it, so an inactive feature just doesn't show anything.
 */
export default async () => {
  render(<Extension />, document.body);
};

function Extension() {
  const [data, setData] = useState(null);
  const [surveyAnswer, setSurveyAnswer] = useState(null);
  const [surveySubmitted, setSurveySubmitted] = useState(false);

  useEffect(() => {
    const order = shopify.orderConfirmation?.value?.order;

    shopify.sessionToken.get().then((token) =>
      fetch(`${APP_URL}/thank-you/data`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify({
          order_id: order?.id,
          is_first_order: shopify.orderConfirmation?.value?.isFirstOrder,
        }),
      })
        .then((res) => (res.ok ? res.json() : null))
        .then(setData)
        .catch(() => setData(null))
    );
  }, []);

  if (!data) {
    return null;
  }

  const submitSurvey = async () => {
    if (!surveyAnswer) return;

    const token = await shopify.sessionToken.get();

    fetch(`${APP_URL}/thank-you/survey`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
      body: JSON.stringify({
        order_id: shopify.orderConfirmation?.value?.order?.id,
        question_id: data.survey?.id,
        answer: surveyAnswer,
      }),
    }).finally(() => setSurveySubmitted(true));
  };

  return (
    <s-stack direction="block" gap="loose">
      {data.cross_sell && (
        <s-banner heading={data.cross_sell.heading}>
          <s-stack direction="block" gap="tight">
            <s-text>{data.cross_sell.description}</s-text>
            {data.cross_sell.discount_code && (
              <s-text>
                Use code <s-text type="strong">{data.cross_sell.discount_code}</s-text> on your next order.
              </s-text>
            )}
          </s-stack>
        </s-banner>
      )}

      {data.survey && !surveySubmitted && (
        <s-section heading={data.survey.question}>
          <s-stack direction="block" gap="base">
            <s-choice-list name="vantora-survey" onChange={(e) => setSurveyAnswer(e.target.value)}>
              {data.survey.options.map((option) => (
                <s-choice key={option} value={option}>
                  {option}
                </s-choice>
              ))}
            </s-choice-list>
            <s-button onClick={submitSurvey}>Submit</s-button>
          </s-stack>
        </s-section>
      )}
      {surveySubmitted && <s-text>Thanks for letting us know!</s-text>}

      {data.referral && (
        <s-section heading="Share and earn">
          <s-stack direction="block" gap="tight">
            <s-text>{data.referral.description}</s-text>
            <s-link href={data.referral.link}>{data.referral.link}</s-link>
          </s-stack>
        </s-section>
      )}
    </s-stack>
  );
}
