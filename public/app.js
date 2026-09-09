'use strict';
const $ = id => document.getElementById(id);
let topics = [], state = null, selected = null, busy = false;
let requestId = crypto.randomUUID();
let clockOffset = 0, openingCheck = false;
const dateText = date => new Date(date+'T12:00:00-06:00').toLocaleDateString('en-CA',{timeZone:'America/Regina',weekday:'long',month:'long',day:'numeric'});
function render(){
 $('student').disabled=Boolean(state?.ownTopicId);
 $('reserve').disabled=busy||Boolean(state?.ownTopicId);
 if(state?.ownTopicId){
  const own=topics.find(t=>t.id===state.ownTopicId);
  showReceipt(own);
  $('message').textContent=own?`This browser has reserved ${own.title} on ${dateText(own.date)}. You cannot choose another topic. Contact your instructor if you need a change.`:'This browser already has a reservation.';
  if($('confirm').open)$('confirm').close();
 }
 if(!state?.ownTopicId)$('receipt').hidden=true;
 $('topics').replaceChildren();
 for(const topic of topics){
  const card=document.createElement('article');card.className='card';
  const date=document.createElement('span');date.className='date';date.textContent=dateText(topic.date);
  const title=document.createElement('h2');title.textContent=topic.title;
  const question=document.createElement('p');question.textContent=topic.question;
  const button=document.createElement('button');
  const taken=state?.taken?.includes(topic.id);card.classList.toggle('taken',Boolean(taken));
  button.textContent=state?.ownTopicId===topic.id?'Your reserved topic':state?.ownTopicId?'One topic per browser':taken?'Already chosen':state?.open?'Choose this topic':'Not yet available';
  button.disabled=!state?.open||taken||busy||Boolean(state?.ownTopicId);
  button.onclick=()=>{const name=$('student').value.trim();if(!name){$('message').textContent='Enter your name first.';$('student').focus();return;}if(selected?.id!==topic.id)requestId=crypto.randomUUID();selected=topic;$('confirm-title').textContent=topic.title;$('confirm-date').textContent=dateText(topic.date);$('confirm-name').textContent=name;$('dialog-message').textContent='';$('decision-stamp').hidden=true;$('reserve').hidden=false;$('return-topics').textContent='Go back';$('confirm').showModal();};
  const engraving=document.createElement('div');engraving.className='topic-engraving';engraving.setAttribute('aria-hidden','true');const iconIndex=topics.indexOf(topic);engraving.style.backgroundPosition=`${(iconIndex%5)*25}% ${iconIndex<5?0:100}%`;card.append(date,title,engraving,question,button);$('topics').append(card);
 }
}
async function refresh(){
 try{const started=Date.now();const response=await fetch('api.php',{cache:'no-store'});if(!response.ok)throw Error();state=await response.json();clockOffset=state.serverTimeMs-(started+Date.now())/2;openingCheck=false;$('status').textContent=state.open?'Signup is open. Choose an available topic.':'Topic signup has not opened yet.';}
 catch{state=null;$('status').textContent='Signup is unavailable right now. Please try again shortly.';}
 render();countdown();
}
$('reserve').onclick=async()=>{
 if(busy||!selected||state?.ownTopicId)return;busy=true;$('reserve').disabled=true;
 try{
  const response=await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({requestId,topicId:selected.id,name:$('student').value.trim()})});
  const result=await response.json();
  if(!response.ok){
   $('decision-stamp').textContent=response.status===409?'REQUEST DENIED':response.status===403?'NOT YET AUTHORIZED':'NOT CONFIRMED';
   $('decision-stamp').hidden=false;
   $('dialog-message').textContent=result.message||'Unable to reserve this topic. Please try again.';
   $('return-topics').textContent=response.status===409?'Choose another topic':'Go back';
   $('reserve').hidden=response.status===409;
   await refresh();return;
  }
  state={...state,ownTopicId:selected.id};showReceipt(selected);$('confirm').close();$('receipt').scrollIntoView({block:'center',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});$('message').textContent=`You are confirmed for ${selected.title} on ${dateText(selected.date)}. Save this confirmation for your records.`;await refresh();
 }catch{$('decision-stamp').textContent='NOT CONFIRMED';$('decision-stamp').hidden=false;$('dialog-message').textContent='We could not confirm the result. Please retry with the same name and topic.';}
 finally{busy=false;$('reserve').disabled=false;render();}
};
(async()=>{try{const r=await fetch('topics.json');if(!r.ok)throw Error();topics=await r.json();await refresh();setInterval(refresh,10000);}catch{$('status').textContent='Topics could not be loaded. Please refresh the page.';}})();

function showReceipt(topic){
 if(!topic)return;
 $('receipt-title').textContent=topic.title;$('receipt-date').textContent=dateText(topic.date);$('receipt').hidden=false;
}
$('return-topics').addEventListener('click',()=>{if($('return-topics').textContent==='Choose another topic'){refresh();document.querySelector('.topic-heading').scrollIntoView({block:'start'});}});
function countdown(){
 const panel=document.querySelector('.launch');
 if(!state){$('countdown').textContent='';$('authorization').textContent='AWAITING VERIFICATION';$('authorization').className='stamp pending';$('urgency').textContent='Checking with the office.';panel.classList.remove('is-open','finale');return;}
 if(state.open){
  if(!panel.classList.contains('is-open')){$('authorization').className='stamp approved stamp-arrival';}
  $('countdown').textContent='THE FLOOR IS OPEN';$('authorization').textContent='SELECTION AUTHORIZED';
  $('urgency').textContent='The committee has approved your decision to make a decision.';
  document.querySelector('.launch-kicker').textContent='Your selection window is open.';
  panel.classList.add('is-open');panel.classList.remove('finale');return;
 }
 panel.classList.remove('is-open');$('authorization').textContent='NOT YET AUTHORIZED';$('authorization').className='stamp pending';
 const remaining=Math.max(0,new Date(state.opensAt).getTime()-(Date.now()+clockOffset));
 panel.classList.toggle('finale',remaining>0&&remaining<60000);
 $('urgency').textContent=remaining<=60000?'Someone has removed the stamp’s protective cover.':remaining<=300000?'Senior administrators are hovering.':remaining<=3600000?'The committee has been recalled.':'The paperwork is proceeding.';
 const total=Math.ceil(remaining/1000),days=Math.floor(total/86400),hours=Math.floor(total%86400/3600),minutes=Math.floor(total%3600/60),seconds=total%60;
 const values=[['DAYS',days],['HOURS',hours],['MINUTES',minutes],['SECONDS',seconds]];
 if(!$('countdown').querySelector('.clock-unit')){
  $('countdown').replaceChildren();
  for(const [label] of values){const unit=document.createElement('span');unit.className='clock-unit';const digit=document.createElement('b');const caption=document.createElement('small');caption.textContent=label;unit.append(digit,caption);$('countdown').append(unit);}
 }
 [...$('countdown').querySelectorAll('b')].forEach((el,i)=>{
  const next=String(values[i][1]).padStart(2,'0');
  if(el.dataset.value===next)return;
  const previous=el.dataset.value;el.dataset.value=next;
  el.setAttribute('aria-label',next);el.replaceChildren();
  const face=document.createElement('span');face.className='clock-value';face.textContent=next;face.setAttribute('aria-hidden','true');el.append(face);
  if(previous&&!matchMedia('(prefers-reduced-motion: reduce)').matches){
   const top=document.createElement('span'),bottom=document.createElement('span');
   top.className='flap flap-top';top.textContent=previous;
   bottom.className='flap flap-bottom';bottom.textContent=next;
   top.setAttribute('aria-hidden','true');bottom.setAttribute('aria-hidden','true');
   el.append(top,bottom);
  }
 });
 if(remaining===0&&!openingCheck){openingCheck=true;refresh();}
}
setInterval(countdown,200);
const launchJokes=[
 'This countdown could have been a discussion post.',
 'Your request to learn has been forwarded to the appropriate committee.',
 'No additional forms are required. We are as surprised as you are.',
 'The minutes from the meeting about the minutes are pending.',
 'Innovation has been approved, subject to no actual changes.',
 'This process has been peer-reviewed by another process.',
 'The stamp has been aligned with the learning outcomes.',
 'Please retain a copy of your curiosity for your records.',
 'A working group is investigating whether this needed a working group.',
 'Your topic is approximately sixty minutes. The paperwork is eternal.'
];
let jokeIndex=0;
setInterval(()=>{$('launch-joke').textContent=launchJokes[++jokeIndex%launchJokes.length];},9000);
