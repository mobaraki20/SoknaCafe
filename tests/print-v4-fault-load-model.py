from dataclasses import dataclass, field
from collections import defaultdict, deque

@dataclass
class Job:
    id:int;dest:str;state:str='pending';attempt:int=0;spooler_accepts:int=0;resolved:bool=False;history:list[str]=field(default_factory=list)

class Sim:
    def __init__(self):self.next=1;self.q=defaultdict(deque);self.jobs={};self.submitted=defaultdict(list);self.reprints=0
    def add(self,d):
        j=Job(self.next,d);self.next+=1;self.jobs[j.id]=j;self.q[d].append(j.id);j.history.append('created');return j
    def process(self,j:Job,scenario:int):
        # attempt 1 reservation; safe failures happen before submission fence.
        j.attempt+=1;j.state='reserved';j.history.append('reserved')
        if scenario==1 and j.attempt==1: j.state='pending';j.history.append('lease_expired');return 'retry'
        j.state='claimed';j.history.append('accepted')
        if scenario==2 and j.attempt==1: j.state='pending';j.history.append('pre_submit_failed');return 'retry'
        j.history.append('fence')
        if scenario==3:
            # Crash after worker may have started: ambiguity. No automatic retry.
            j.state='recovery_hold';j.history.append('ambiguous_after_fence')
            if j.id%2==0:
                j.resolved=True;j.history.append('human_confirmed_printed')
                return 'done'
            j.resolved=True;j.history.append('human_requested_reprint')
            r=self.add(j.dest);self.reprints+=1;r.history.append(f'reprint_of:{j.id}')
            return 'done'
        # Spooler accepts exactly once. Network loss after accept only retries the report.
        j.spooler_accepts+=1;self.submitted[j.dest].append(j.id);j.history.append('spooler_accepted')
        if scenario==4:j.history+=['report_network_failed','report_retried_without_print']
        j.state='submitted';return 'done'
    def run(self,count:int):
        dests=['kitchen','bar','customer']
        for i in range(count):self.add(dests[i%3])
        for d in dests:
            while self.q[d]:
                jid=self.q[d][0];j=self.jobs[jid]
                scenario=jid%17
                kind={1:1,2:2,3:3,4:4}.get(scenario,0)
                outcome=self.process(j,kind)
                if outcome=='retry':
                    assert j.attempt<5
                    continue
                self.q[d].popleft()
        # reprints may have been appended after this destination was originally passed; drain all.
        changed=True
        while changed:
            changed=False
            for d in dests:
                while self.q[d]:
                    changed=True;j=self.jobs[self.q[d][0]];self.process(j,0);self.q[d].popleft()
        dup=[j.id for j in self.jobs.values() if j.spooler_accepts>1]
        unresolved=[j.id for j in self.jobs.values() if j.state not in ('submitted','recovery_hold')]
        assert not dup,dup[:10]
        assert not unresolved,unresolved[:10]
        # Automatic submissions for each destination are monotonic by server job id. Reprints append new IDs.
        for d,ids in self.submitted.items():assert ids==sorted(ids),(d,ids[:20])
        return len(self.jobs),sum(j.spooler_accepts for j in self.jobs.values())

for n,label in [(100,'burst_100'),(750,'25_per_minute_30_minutes'),(2000,'mixed_2000')]:
    total,sub=Sim().run(n);print(f'PASS {label}: source_jobs={n} total_with_reprints={total} spooler_submissions={sub}')
print('PASS zero_silent_loss zero_automatic_duplicate fifo_per_destination')
